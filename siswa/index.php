<?php
// portal siswa v5 - laragon: http://localhost/siswa  (db dibuat otomatis)
session_start();date_default_timezone_set('Asia/Jakarta');
(new PDO('mysql:host=localhost;charset=utf8mb4','root',''))->exec("CREATE DATABASE IF NOT EXISTS siswa_app CHARACTER SET utf8mb4");
$db=new PDO('mysql:host=localhost;dbname=siswa_app;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach(["users(id INT AUTO_INCREMENT PRIMARY KEY,nama VARCHAR(80),username VARCHAR(40) UNIQUE,password VARCHAR(255),role VARCHAR(10) DEFAULT 'siswa',kelas VARCHAR(20),bio TEXT,xp INT DEFAULT 0,foto VARCHAR(80))",
"catatan(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,judul VARCHAR(120),isi TEXT,warna INT DEFAULT 0,tgl DATETIME DEFAULT CURRENT_TIMESTAMP)",
"tugas(id INT AUTO_INCREMENT PRIMARY KEY,judul VARCHAR(120),isi TEXT,deadline DATE)",
"selesai(user_id INT,tugas_id INT,PRIMARY KEY(user_id,tugas_id))",
"nilai(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,mapel VARCHAR(60),skor INT)",
"lampiran(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,tipe VARCHAR(10),isi TEXT,tgl DATETIME DEFAULT CURRENT_TIMESTAMP)"] as $t) $db->exec("CREATE TABLE IF NOT EXISTS $t");
function q($s,$a=[]){global $db;$r=$db->prepare($s);$r->execute($a);return $r;}
if(!q("SHOW COLUMNS FROM users LIKE 'foto'")->fetch())$db->exec("ALTER TABLE users ADD foto VARCHAR(80)");
if(!q("SELECT 1 FROM users")->fetch())q("INSERT INTO users(nama,username,password,role) VALUES('administrator','admin',?,'admin')",[password_hash('admin123',PASSWORD_DEFAULT)]);
function h($s){return htmlspecialchars($s??'',ENT_QUOTES);}
function go($p,$m=''){if($m)$_SESSION['m']=$m;header("Location: ?p=".preg_replace('/\W/','',$p));exit;}
// level: lv1->lv2 butuh 100 xp, tiap level berikutnya syaratnya naik 2% dari level sebelumnya
// return [level, xp di level ini, xp yang dibutuhkan untuk naik]
function lvinfo($x){$l=1;$n=100;while($x>=$n){$x-=$n;$l++;$n=(int)round(100*pow(1.02,$l-1));}return[$l,$x,$n];}
$_SESSION['t']??=bin2hex(random_bytes(16));
function f(){return '<input type=hidden name=t value="'.$_SESSION['t'].'">';}
function av($r,$c=''){return preg_match('/^pp[1-5]\.png$/',$r['foto']??'')?"<img class='av $c' src='{$r['foto']}' alt=''>":"<span class='av $c'>".h(mb_strtoupper(mb_substr($r['nama']?:'?',0,1)))."</span>";}
function eye(){return "<button type=button class=eye onclick=\"var i=this.previousElementSibling;i.type=i.type=='password'?'text':'password';this.classList.toggle('on')\" aria-label='lihat sandi'><svg class=e1 viewBox='0 0 9 5'><path d='M2 0h5v1h-5zM1 1h1v1h-1zM7 1h1v1h-1zM4 1h1v1h-1zM0 2h1v1h-1zM3 2h3v1h-3zM8 2h1v1h-1zM1 3h1v1h-1zM7 3h1v1h-1zM4 3h1v1h-1zM2 4h5v1h-5z'/></svg><svg class=e2 viewBox='0 0 9 5'><path d='M0 2h2v1h-2zM2 3h5v1h-5zM7 2h2v1h-2zM2 4h1v1h-1zM4 4h1v1h-1zM6 4h1v1h-1z'/></svg></button>";}
$u=isset($_SESSION['uid'])?q("SELECT * FROM users WHERE id=?",[$_SESSION['uid']])->fetch():null;
$adm=$u&&$u['role']=='admin';
$p=$_GET['p']??'dashboard';
if(!$u&&!in_array($p,['login','register']))$p='login';
if($u&&in_array($p,['login','register']))$p='dashboard';

if($_SERVER['REQUEST_METHOD']=='POST'){
 if(!hash_equals($_SESSION['t'],$_POST['t']??''))die('token tidak valid');
 $a=$_POST['a']??'';$P=fn($k)=>trim($_POST[$k]??'');
 if($a=='login'){$r=q("SELECT * FROM users WHERE username=?",[$P('username')])->fetch();
  if($r&&password_verify($_POST['password'],$r['password'])){session_regenerate_id(true);$_SESSION['uid']=$r['id'];go('dashboard');}
  $_SESSION['salah']=1;go('login','username atau sandi salah');}
 if($a=='register'){
  if(strlen($P('username'))<4||strlen($_POST['password'])<6)go('register','username min 4 huruf, sandi min 6 huruf');
  if(q("SELECT 1 FROM users WHERE username=?",[$P('username')])->fetch())go('register','username sudah dipakai');
  q("INSERT INTO users(nama,username,password,kelas) VALUES(?,?,?,?)",[$P('nama'),$P('username'),password_hash($_POST['password'],PASSWORD_DEFAULT),$P('kelas')]);
  go('login','akun dibuat, silakan masuk');}
 if(!$u)go('login');
 if($a=='xp'){ // hadiah xp minigames (maks 15 xp per permainan, ada jeda minimal agar tidak bisa di-spam)
  header('Content-Type: application/json');
  $g=$_POST['g']??'';$s=(int)($_POST['s']??0);
  $tbl=['puzzle_easy'=>[3,5],'puzzle_medium'=>[6,8],'puzzle_hard'=>[10,12],'kuis'=>[12,30],'labirin'=>[15,30]]; // [xp maks, jeda detik]
  $gain=0;$wait=false;
  if(isset($tbl[$g])){
   [$mx,$gap]=$tbl[$g];
   $gain=$g=='kuis'?(int)round(max(0,min(10,$s))/10*$mx):$mx;
   $gain=min(15,$gain);
   if(time()-($_SESSION['gx'][$g]??0)<$gap){$gain=0;$wait=true;}
   else $_SESSION['gx'][$g]=time();
   if($gain>0)q("UPDATE users SET xp=xp+? WHERE id=?",[$gain,$u['id']]);
  }
  $x=(int)q("SELECT xp FROM users WHERE id=?",[$u['id']])->fetch()['xp'];
  [$l,$c,$n]=lvinfo($x);
  echo json_encode(['xp'=>$gain,'wait'=>$wait,'lvl'=>$l,'cur'=>$c,'need'=>$n]);exit;}
 if($a=='logout'){session_destroy();header('Location: ?p=login');exit;}
 if($a=='profil'){
  if(preg_match('/^pp[1-5]\.png$/',$_POST['pp']??''))q("UPDATE users SET foto=? WHERE id=?",[$_POST['pp'],$u['id']]);
  q("UPDATE users SET nama=?,kelas=?,bio=? WHERE id=?",[$P('nama'),$P('kelas'),$P('bio'),$u['id']]);
  if($_POST['password']!==''){if(strlen($_POST['password'])<6)go('profil','sandi baru min 6 huruf');q("UPDATE users SET password=? WHERE id=?",[password_hash($_POST['password'],PASSWORD_DEFAULT),$u['id']]);}
  go('profil','profil disimpan');}
 if($a=='centang'){$id=(int)$_POST['id'];
  if(q("SELECT 1 FROM selesai WHERE user_id=? AND tugas_id=?",[$u['id'],$id])->fetch()){q("DELETE FROM selesai WHERE user_id=? AND tugas_id=?",[$u['id'],$id]);q("UPDATE users SET xp=GREATEST(xp-10,0) WHERE id=?",[$u['id']]);}
  else{q("INSERT INTO selesai VALUES(?,?)",[$u['id'],$id]);q("UPDATE users SET xp=xp+10 WHERE id=?",[$u['id']]);}
  go('tugas');}
 if($a=='catatan'){q("INSERT INTO catatan(user_id,judul,isi,warna) VALUES(?,?,?,?)",[$u['id'],$P('judul'),$P('isi'),(int)$_POST['warna']%4]);go('catatan','catatan disimpan');}
 if($a=='foto'){$fl=$_FILES['foto']??null;
  if(!$fl||$fl['error']!==UPLOAD_ERR_OK)go('tugas','foto gagal diunggah');
  $ex=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'][(new finfo(FILEINFO_MIME_TYPE))->file($fl['tmp_name'])]??null;
  if(!$ex||$fl['size']>5*1024*1024)go('tugas','file harus gambar, maks 5 mb');
  if(!is_dir(__DIR__.'/uploads'))mkdir(__DIR__.'/uploads');
  $nm=bin2hex(random_bytes(8)).".$ex";move_uploaded_file($fl['tmp_name'],__DIR__."/uploads/$nm");
  q("INSERT INTO lampiran(user_id,tipe,isi) VALUES(?,?,?)",[$u['id'],'foto',$nm]);go('tugas','foto ditambahkan');}
 if($a=='teks'){if($P('isi')==='')go('tugas','teks kosong');q("INSERT INTO lampiran(user_id,tipe,isi) VALUES(?,?,?)",[$u['id'],'teks',$P('isi')]);go('tugas','teks ditambahkan');}
 if($a=='nilai'){q("INSERT INTO nilai(user_id,mapel,skor) VALUES(?,?,?)",[$adm?(int)$_POST['uid']:$u['id'],$P('mapel'),min(100,max(0,(int)$_POST['skor']))]);go('nilai','nilai ditambahkan');}
 if($a=='hapus'){$tb=$_POST['tb'];$id=(int)$_POST['id'];
  if($tb=='nilai')q("DELETE FROM nilai WHERE id=?".($adm?'':" AND user_id=?"),$adm?[$id]:[$id,$u['id']]);
  elseif($tb=='catatan')q("DELETE FROM catatan WHERE id=? AND user_id=?",[$id,$u['id']]);
  elseif($tb=='lampiran'){$r=q("SELECT * FROM lampiran WHERE id=? AND user_id=?",[$id,$u['id']])->fetch();
   if($r){if($r['tipe']=='foto'&&preg_match('/^[a-f0-9]{16}\.(jpg|png|gif|webp)$/',$r['isi']))@unlink(__DIR__."/uploads/".$r['isi']);q("DELETE FROM lampiran WHERE id=?",[$id]);}}
  elseif($adm&&$tb=='tugas')q("DELETE FROM tugas WHERE id=?",[$id]);
  elseif($adm&&$tb=='users'&&$id!=$u['id'])q("DELETE FROM users WHERE id=?",[$id]);
  go($_POST['back'],'dihapus');}
 if($adm&&$a=='tugas'){q("INSERT INTO tugas(judul,isi,deadline) VALUES(?,?,?)",[$P('judul'),$P('isi'),$P('deadline')]);go('tugas','tugas dibuat');}
 go($p);
}
$m=$_SESSION['m']??'';$salah=$_SESSION['salah']??0;unset($_SESSION['m'],$_SESSION['salah']);
[$lvl,$lx,$ln]=$u?lvinfo((int)$u['xp']):[0,0,100];
$menu=['dashboard'=>'beranda','catatan'=>'catatan','tugas'=>'tugas','nilai'=>'nilai','musik'=>'musik','profil'=>'profil']+($adm?['admin'=>'siswa']:[]);
$del=fn($tb,$id)=>"<form method=post style=display:inline>".f()."<input type=hidden name=a value=hapus><input type=hidden name=tb value=$tb><input type=hidden name=id value=$id><input type=hidden name=back value=$GLOBALS[p]><button type=button class=x onclick=\"dx(this)\">hapus</button></form>";
?><!doctype html><html lang=id><head><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1">
<title>portal siswa</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap" rel=stylesheet>
<style>
:root{
 --bg:#cfe5ff;--card:#fbfdff;--ink:#2d3f78;--bd:#7aa6e6;--sd:#aac9f2;--ac:#93c1fb;--y:#c8e0ff;--ok:#9fe3c3;--no:#ffc2cc;--inp:#f0f7ff;--mut:#7184b8;
 --bgi:linear-gradient(180deg,#bddbff 0%,#d4e9ff 35%,#ecf5ff 70%,#f9fcff 100%);--cl:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 4' shape-rendering='crispEdges'%3E%3Cg fill='%2377aeee' fill-opacity='.3'%3E%3Crect x='3' y='0' width='6' height='1'/%3E%3Crect x='0' y='1' width='12' height='3'/%3E%3C/g%3E%3C/svg%3E")}
[data-d]{--bg:#141a3a;--card:#232c5c;--ink:#e8f0ff;--bd:#5d78c9;--sd:#0a0f28;--ac:#4a6fc4;--y:#34437f;--ok:#2f8f6a;--no:#a83b52;--inp:#141a3a;--mut:#9db2f0;--bgi:linear-gradient(180deg,#232f66 0%,#1a2250 50%,#141a3a 100%);--cl:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 4' shape-rendering='crispEdges'%3E%3Cg fill='%23ffffff' fill-opacity='.07'%3E%3Crect x='3' y='0' width='6' height='1'/%3E%3Crect x='0' y='1' width='12' height='3'/%3E%3C/g%3E%3C/svg%3E")}
*{box-sizing:border-box;border-radius:0}
body{margin:0;color:var(--ink);font:1.4rem/1.2 VT323,monospace;text-transform:lowercase;background-color:var(--bg);background-image:var(--cl),var(--cl),var(--cl),var(--cl),var(--bgi);background-repeat:no-repeat;background-size:144px 48px,108px 36px,180px 60px,126px 42px,100% 100%;background-position:86% 10%,66% 22%,8% 34%,96% 46%,0 0;background-attachment:fixed}
h1,h2,h3,button,.n,nav a,th,label{font-family:'Press Start 2P',monospace;font-weight:400;line-height:1.5}
h1{font-size:1.2rem;margin:0 0 1.4rem}h3{font-size:.8rem;margin:0 0 .6rem}label{font-size:.6rem;display:block;margin-bottom:.4rem}
a{color:var(--ink)}
.wrap{display:grid;grid-template-columns:230px 1fr;gap:1.6rem;padding:1.2rem;min-height:100vh}
.c,nav{background:var(--card);border:4px solid var(--bd);box-shadow:6px 6px 0 var(--sd)}
.c{padding:1.2rem;margin-bottom:1.6rem}nav{padding:1rem;position:sticky;top:1.2rem;height:calc(100vh - 2.4rem);display:flex;flex-direction:column;gap:.5rem}
nav .me{display:flex;align-items:center;gap:.7rem;margin-bottom:1rem}nav .me b{font:400 .65rem 'Press Start 2P'}nav .me small{font-size:1.1rem;color:var(--mut)}
nav a{color:var(--ink);text-decoration:none;padding:.7rem;font-size:.62rem;border:3px solid transparent}nav a:hover{border-color:var(--bd)}nav a.on{background:var(--y);border-color:var(--bd);color:var(--ink)}nav .sp{flex:1}
.av{width:46px;height:46px;border-radius:50%;object-fit:cover;image-rendering:pixelated;border:3px solid var(--bd);background:var(--y);color:var(--ink);display:inline-flex;align-items:center;justify-content:center;font:400 .9rem 'Press Start 2P';flex:none}.av.xl{width:150px;height:150px;font-size:2.4rem;border-width:4px}
main{width:100%;max-width:1000px}.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1.6rem;margin-bottom:1.6rem}.g .c{margin:0}
.n{font-size:1.5rem}.mut{color:var(--mut)}
.clk{background:var(--y);color:var(--ink);text-align:center;padding:.8rem 1.2rem;margin:0}.clk .mut{color:var(--ink)}
.bar{height:18px;border:3px solid var(--bd);background:var(--inp);margin:.5rem 0}.bar i{display:block;height:100%;background:repeating-linear-gradient(90deg,#6aa2f0 0 8px,transparent 8px 10px)}
input,textarea,select{width:100%;padding:.6rem .7rem;margin:0 0 1rem;border:3px solid var(--bd);background:var(--inp);color:var(--ink);font:1.4rem VT323,monospace;text-transform:none;box-shadow:inset 4px 4px 0 rgba(122,166,230,.25)}
input:focus,textarea:focus,select:focus{outline:4px solid var(--ac);outline-offset:0}
button{background:var(--ac);color:var(--ink);border:3px solid var(--bd);padding:.8rem 1rem;font-size:.62rem;cursor:pointer;box-shadow:4px 4px 0 var(--sd);text-transform:lowercase}
button:hover{background:var(--y)}button:active{transform:translate(4px,4px);box-shadow:none}button.x{background:none;color:#e0455a;border:0;box-shadow:none;padding:.3rem}button.o{background:var(--card);color:var(--ink)}
.pw{position:relative}.pw input{padding-right:3.4rem}.eye{position:absolute;right:6px;top:5px;width:40px;height:40px;padding:6px;background:var(--y);color:var(--ink);box-shadow:none}.eye svg{width:100%;height:100%;fill:currentColor;shape-rendering:crispEdges}.eye .e2,.eye.on .e1{display:none}.eye.on .e2{display:block}
.m{background:var(--y);color:var(--ink);border:4px solid var(--bd);padding:.7rem 1rem;margin-bottom:1.4rem;box-shadow:4px 4px 0 var(--sd)}
.row{display:flex;justify-content:space-between;align-items:center;gap:1rem}table{width:100%;border-collapse:collapse}td,th{padding:.7rem .4rem;border-bottom:3px dashed var(--bd);text-align:left}th{font-size:.55rem;color:var(--mut)}
.auth{max-width:440px;margin:5vh auto;padding:1rem}.auth h1{text-align:center}.cat{text-align:center;margin-bottom:.8rem}.cat img{height:120px;image-rendering:pixelated}
.col2{display:grid;grid-template-columns:1fr 300px;gap:1.6rem;align-items:start}.ph2{text-align:center}
.pick{display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;margin:1rem 0}
.ppb{padding:4px;border-radius:50%;background:var(--y);box-shadow:none}.ppb img{width:64px;height:64px;border-radius:50%;image-rendering:pixelated;display:block}.ppb.sel{box-shadow:0 0 0 4px var(--ac);background:var(--ac)}
.mod{display:none;position:fixed;inset:0;background:rgba(45,63,120,.35);z-index:50;align-items:center;justify-content:center;padding:1rem}.mod.on{display:flex}.mc{max-width:440px;width:100%;margin:0;text-align:center}
.notes{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.4rem}.nt{border:4px solid var(--bd);box-shadow:6px 6px 0 var(--sd);padding:1rem;color:#2d3f78}.nt p{white-space:pre-wrap;margin:.5rem 0}
.w0{background:#fff5bf}.w1{background:#d6e8ff}.w2{background:#e6deff}.w3{background:#d3f3e4}
.gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem}.gal figure{margin:0}.gal img,.gal .pl{width:100%;aspect-ratio:4/3;object-fit:cover;border:3px solid var(--bd);display:block;background:var(--inp)}.gal .pl{display:flex;align-items:center;justify-content:center;text-align:center;color:var(--mut);padding:.4rem}.gal figcaption{text-align:center;margin-top:.3rem}
.ibs{display:flex;gap:1rem;margin:1rem 0 1.4rem}.ib{display:flex;flex-direction:column;align-items:center;gap:.6rem;width:96px;padding:.9rem .4rem;background:var(--card);font-size:.55rem}.ib svg{width:44px;height:44px;fill:currentColor;shape-rendering:crispEdges}
.lp{width:100%;aspect-ratio:4/3;object-fit:cover;border:3px solid var(--bd);display:block;margin-bottom:.5rem;background:var(--inp)}
/* minigames */
.tabs{display:flex;gap:.6rem;flex-wrap:wrap;margin:0 0 1.2rem}.tabs button{background:var(--card)}.tabs button.on{background:var(--ac)}
.gm{display:none}.gm.on{display:block}.gmsg{min-height:1.4rem;margin:.8rem 0 0}
.eq{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;font-size:2rem;margin:1rem 0}.eq .nm{min-width:40px;text-align:center}
.eq button.sl{width:56px;height:56px;padding:0;font-size:1rem;background:var(--y)}
.ans{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.ans button{font-size:.8rem;padding:1.1rem;background:var(--card)}.ans button.ok{background:var(--ok)}.ans button.no{background:var(--no)}
.big{font-size:2rem}
.mz{--cs:clamp(16px,4.8vw,26px);display:grid;grid-template-columns:repeat(var(--w),var(--cs));width:max-content;max-width:100%;margin:1rem auto;border:4px solid var(--bd);box-shadow:4px 4px 0 var(--sd)}
.mz i{width:var(--cs);height:var(--cs);display:flex;align-items:center;justify-content:center;font:normal 400 .5rem 'Press Start 2P',monospace}
.mz .w{background:#86afe6}.mz .f{background:var(--inp)}.mz .d{background:#ffe9a6;color:#2d3f78}.mz .e{background:var(--ok)}.mz .p{background:#4a86e8;box-shadow:inset 0 0 0 3px var(--inp)}
.dp{display:grid;grid-template-columns:repeat(3,58px);gap:.4rem;justify-content:center;margin-top:1rem}.dp button{padding:.6rem 0}.dp .u{grid-column:2}
#dq{display:none;align-items:center;gap:.8rem;flex-wrap:wrap;justify-content:center}#dq.on{display:flex}#dq input{width:110px;margin:0}
/* musik */
.stage{position:relative;display:flex;gap:2.4rem;flex-wrap:wrap;align-items:center;justify-content:center;padding:1rem 0}
.tt{position:relative;width:300px;height:300px;background:var(--y);border:4px solid var(--bd);box-shadow:6px 6px 0 var(--sd);display:flex;align-items:center;justify-content:center}
.disc{position:relative;width:230px;height:230px;border-radius:50%;border:4px solid #1d2a5c;background:conic-gradient(from 0deg,transparent 0 38%,rgba(255,255,255,.22) 42% 48%,transparent 52% 88%,rgba(255,255,255,.22) 92% 98%,transparent 100%),repeating-radial-gradient(circle,#26356f 0 3px,#31438a 3px 6px)}
.disc::after{content:'';position:absolute;left:50%;top:50%;width:64px;height:64px;margin:-32px 0 0 -32px;border-radius:50%;background:var(--ac);border:4px solid #1d2a5c}
.disc.sp{animation:spin 2.4s steps(40) infinite}
.arm{position:absolute;right:20px;top:16px;width:12px;height:150px;background:var(--bd);border:3px solid var(--ink);transform-origin:50% 6px;transform:rotate(-18deg);transition:transform .5s steps(8);z-index:2}
.arm::after{content:'';position:absolute;left:-7px;bottom:-12px;width:20px;height:14px;background:var(--ink)}
.arm.on{transform:rotate(20deg)}
.rack{display:flex;align-items:center;justify-content:center;width:200px;height:130px;border:4px dashed var(--bd);color:var(--mut);text-align:center;padding:.5rem}
.ks{position:absolute;left:0;top:0;width:130px;height:84px;background:#e6deff;border:4px solid var(--ink);box-shadow:4px 4px 0 var(--sd);cursor:pointer;touch-action:none;user-select:none;-webkit-user-select:none;z-index:5;transition:left .18s steps(6),top .18s steps(6)}
.ks.grab{cursor:grabbing;transition:none;box-shadow:8px 8px 0 var(--sd)}.ks:focus-visible{outline:4px solid var(--ac)}
.ks .kl{margin:6px 6px 0;height:38px;background:#fff5bf;border:3px solid var(--ink);color:#2d3f78;padding:3px 4px;text-align:center;overflow:hidden}
.ks .kl b{display:block;font:400 .42rem/1.4 'Press Start 2P',monospace}.ks .kl small{display:block;font-size:1.05rem;line-height:1}
.ks .kr{display:flex;justify-content:space-around;margin:5px 14px 0}
.rl{display:block;width:16px;height:16px;border-radius:50%;border:3px solid var(--ink);background:conic-gradient(var(--ink) 0 12%,transparent 12% 50%,var(--ink) 50% 62%,transparent 62%)}
.ks.pl .rl{animation:spin 1.2s steps(8) infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@media(prefers-reduced-motion:reduce){.disc.sp,.ks.pl .rl{animation:none}}
.hh{position:fixed;bottom:-30px;width:var(--s);height:var(--s);background:var(--c);-webkit-mask:var(--m) center/contain no-repeat;mask:var(--m) center/contain no-repeat;image-rendering:pixelated;pointer-events:none;z-index:9;opacity:.8;animation:up var(--t) steps(60) forwards}
@keyframes up{to{transform:translateY(-115vh) translateX(var(--x))}}
@media(prefers-reduced-motion:reduce){.hh{display:none}}
@media(max-width:800px){.wrap{grid-template-columns:1fr}nav{height:auto;position:static;flex-direction:row;flex-wrap:wrap;align-items:center}nav .sp,nav .me{display:none}.col2{grid-template-columns:1fr}}

/* spasi beranda lebih lega */
main[data-p=dashboard] .g{gap:2.4rem;margin-bottom:2.4rem}
main[data-p=dashboard]>.c{padding:1.6rem;margin-bottom:2.4rem}
main[data-p=dashboard] h3{margin-bottom:1rem}
main[data-p=dashboard] .c p{margin:.8rem 0}
main[data-p=dashboard] td{padding:.9rem .5rem}
main[data-p=dashboard] .tabs{margin-bottom:1.4rem}
main[data-p=dashboard] .eq{margin:1.6rem 0}
main[data-p=dashboard] .gm .row{margin-top:1rem}

/* mobile umum (portrait & landscape) */
html{-webkit-text-size-adjust:100%}
.mc{max-height:92vh;overflow:auto}
nav{overflow-y:auto}
@media(max-width:800px){
 body{background-attachment:scroll}
 .wrap{grid-template-columns:1fr;gap:1rem;padding:.8rem;min-height:0}
 nav{position:sticky;top:.5rem;z-index:20;height:auto;flex-direction:row;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;gap:.4rem;padding:.5rem}
 nav a,nav form{flex:none}
 nav a{padding:.6rem .7rem;font-size:.55rem;white-space:nowrap}
 nav .sp,nav .me{display:none}
 nav form button{width:auto!important}
 h1{font-size:1rem}
 .g{grid-template-columns:repeat(auto-fit,minmax(160px,1fr))}
 .c{padding:1.1rem}
 main[data-p=dashboard] .g{gap:1.6rem;margin-bottom:1.6rem}
 main[data-p=dashboard]>.c{margin-bottom:1.6rem}
 .clk{width:100%}
 .col2{grid-template-columns:1fr}
 table{display:block;overflow-x:auto;white-space:nowrap}
 .stage{gap:1.4rem}
}
@media(max-width:480px){
 .g{grid-template-columns:1fr}
 .auth{margin:2vh auto}
 .ans button{font-size:.6rem;padding:.9rem .4rem}
 .eq{font-size:1.5rem;gap:.4rem}
 .eq button.sl{width:44px;height:44px}
 .tt{width:250px;height:250px}
 .disc{width:190px;height:190px}
 .arm{height:115px;right:14px}
 .rack{width:150px;height:100px}
 .notes,.gal{grid-template-columns:1fr 1fr}
}
/* landscape di hp (layar pendek) */
@media(max-height:500px) and (orientation:landscape){
 .wrap{min-height:0;padding:.6rem;gap:1rem}
 nav{top:.6rem;height:calc(100vh - 1.2rem);padding:.6rem;gap:.3rem}
 nav .me{display:none}
 nav a{padding:.45rem;font-size:.5rem}
 .auth{margin:1vh auto}.cat img{height:70px}
 .tt{width:230px;height:230px}.disc{width:170px;height:170px}.arm{height:105px}
}
</style><script>if(localStorage.d)document.documentElement.dataset.d=1</script></head><body>
<?php if(!$u): ?><div class=auth><h1>portal siswa</h1>
<?php if($m)echo"<div class=m>".h($m)."</div>"; ?>
<div class=c><form method=post><?=f()?><input type=hidden name=a value="<?=h($p)?>">
<div class=cat><img id=cat src="<?=$salah?'kucing1.gif':'kucing.gif'?>" alt="" onerror="if(this.src.indexOf('kucing1')>0){this.src='kucing.gif'}else this.style.display='none'"></div>
<?php if($p=='register'): ?><label>nama</label><input name=nama required><label>kelas</label><input name=kelas><?php endif ?>
<label>username</label><input name=username required autocomplete=username>
<label>sandi</label><div class=pw><input type=password name=password required autocomplete=current-password><?=eye()?></div>
<button style="width:100%"><?=$p=='login'?'masuk':'buat akun'?></button></form></div>
<p class=mut style="text-align:center"><?=$p=='login'?'belum punya akun? <a href="?p=register">daftar</a>':'sudah punya akun? <a href="?p=login">masuk</a>'?></p></div>
<?php if($salah)echo"<script>setTimeout(function(){document.getElementById('cat').src='kucing.gif'},2500)</script>"; ?>
<?php else: ?><div class=wrap><nav><div class=me><?=av($u)?><div><b><?=h(explode(' ',$u['nama'])[0])?></b><br><small><?=$adm?'admin':h($u['kelas']?:'siswa')?></small></div></div>
<?php foreach($menu as $k=>$v)echo"<a href='?p=$k'".($p==$k?" class=on":"").">$v</a>"; ?>
<span class=sp></span><a href=# onclick="localStorage.d=localStorage.d?'':1;if(!localStorage.d)delete document.documentElement.dataset.d;else document.documentElement.dataset.d=1;return false">mode gelap</a>
<form method=post><?=f()?><input type=hidden name=a value=logout><button class=o style="width:100%">keluar</button></form></nav><main data-p="<?=h($p)?>">
<?php if($m)echo"<div class=m>".h($m)."</div>";
if($p=='dashboard'){
 $tot=q("SELECT COUNT(*) c FROM tugas")->fetch()['c'];$sel=q("SELECT COUNT(*) c FROM selesai WHERE user_id=?",[$u['id']])->fetch()['c'];
 $avg=round(q("SELECT AVG(skor) a FROM nilai WHERE user_id=?",[$u['id']])->fetch()['a']??0,1);$pr=round($lx/$ln*100);?>
 <div class=row style="align-items:flex-start;flex-wrap:wrap;margin-bottom:2.4rem"><div><h1 style="margin:0 0 .5rem">halo, <?=h(explode(' ',$u['nama'])[0])?></h1><span class=mut><?=h($u['kelas'])?></span></div>
 <div class="c clk"><div class=n id=jam>--:--:--</div><div id=tgl>&nbsp;</div></div></div>
 <div class=g><div class=c><h3>level</h3><div class=n id=lvn><?=$lvl?></div><div class=bar><i id=lvb style="width:<?=$pr?>%"></i></div><span class=mut id=lvx><?=$lx?> / <?=$ln?> xp</span></div>
 <div class=c><h3>tugas selesai</h3><div class=n><?=$sel?>/<?=$tot?></div></div>
 <div class=c><h3>rata rata nilai</h3><div class=n><?=$avg?></div></div></div>
 <div class=g><div class=c><h3>catatan terbaru</h3><?php $any=0;foreach(q("SELECT * FROM catatan WHERE user_id=? ORDER BY id DESC LIMIT 3",[$u['id']]) as $r){$any=1;echo"<p><b>".h($r['judul'])."</b><br><span class=mut>".h(mb_strimwidth($r['isi'],0,70,'...'))."</span></p>";}if(!$any)echo"<p class=mut>belum ada catatan</p>"; ?></div>
 <div class=c><h3>peringkat xp</h3><table><?php $i=0;foreach(q("SELECT nama,xp FROM users WHERE role='siswa' ORDER BY xp DESC LIMIT 5") as $r)echo"<tr><td>".++$i."</td><td>".h($r['nama'])."</td><td>{$r['xp']} xp</td></tr>"; ?></table></div></div>

 <div class=c id=mgc><div class=row><h3 style="margin:0">minigames</h3><button type=button class=o id=mgx>tutup ✕</button></div>
 <div id=mgb style="margin-top:1.4rem">
 <div class=tabs id=gt><button class=on>puzzle</button><button>kuis</button><button>labirin</button></div>

 <div class="gm on">
  <div class=tabs id=pl><button data-l=easy class=on>easy</button><button data-l=medium>medium</button><button data-l=hard>hard</button></div>
  <span class=mut>klik kotak ? untuk memilih tanda + − × ÷ sampai hasilnya sama. dihitung dari kiri ke kanan. hadiah: easy 3 xp, medium 6 xp, hard 10 xp.</span>
  <div class=eq id=pq></div>
  <div class=row style="flex-wrap:wrap"><div><button id=pc>cek</button> <button id=pn class=o>soal baru</button></div><span id=ps class=mut></span></div>
  <p id=pm class=gmsg></p>
 </div>

 <div class=gm>
  <div class=row style="flex-wrap:wrap"><span id=qp class=mut></span><span class=mut>hadiah: sampai 12 xp sesuai skor</span></div>
  <div id=qb></div>
 </div>

 <div class=gm>
  <span class=mut>sampai ke kotak hijau (hadiah 15 xp). pintu kuning (?) terkunci, jawab hitungannya untuk membukanya. gerak pakai tombol panah / wasd.</span>
  <div class=mz id=mz></div>
  <div id=dq><b id=dt class=n style="font-size:1.1rem"></b><input type=number id=da autocomplete=off><button id=db>buka</button></div>
  <p id=mm class=gmsg style="text-align:center"></p>
  <div class=dp><button class=u data-d=0,-1>▲</button><button data-d=-1,0>◀</button><button data-d=0,1>▼</button><button data-d=1,0>▶</button></div>
  <div style="text-align:center;margin-top:1rem"><button class=o id=mr>labirin baru</button></div>
 </div>
 </div>
 </div>

 <script>var j=document.getElementById('jam'),g=document.getElementById('tgl');function tk(){var d=new Date();j.textContent=d.toLocaleTimeString('id-ID',{hour12:false}).replace(/\./g,':');g.textContent=d.toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'})}tk();setInterval(tk,1000)</script>
 <script>
(function(){
var $=function(i){return document.getElementById(i)},R=function(a,b){return a+Math.floor(Math.random()*(b-a+1))};
var TK="<?=h($_SESSION['t'])?>",cl=+$('lvn').textContent;
var mb=$('mgb'),mx=$('mgx');
function mset(o){mb.style.display=o?'':'none';mx.textContent=o?'tutup ✕':'buka';try{localStorage.mg=o?'':'0'}catch(e){}}
mx.onclick=function(){mset(mb.style.display=='none')};
try{if(localStorage.mg)mset(false)}catch(e){}
var OPS=['+','−','×','÷'];
function ap(a,o,b){return o=='+'?a+b:o=='−'?a-b:o=='×'?a*b:(b!==0&&a%b===0?a/b:NaN)}
function gq(){var o=OPS[R(0,3)],a,b,r;
 if(o=='+'){a=R(10,99);b=R(10,99);r=a+b}
 else if(o=='−'){a=R(20,99);b=R(5,a-1);r=a-b}
 else if(o=='×'){a=R(3,15);b=R(3,12);r=a*b}
 else{b=R(2,12);r=R(2,15);a=b*r}
 return{t:a+' '+o+' '+b,r:r}}

/* hadiah xp: server yang menghitung & menyimpan, hasilnya dipakai untuk memperbarui kartu level */
function award(g,s,cb){
 var fd=new FormData();fd.append('t',TK);fd.append('a','xp');fd.append('g',g);fd.append('s',s);
 fetch(location.href,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
  var t='';
  if(r.xp>0){t='+'+r.xp+' xp';if(r.lvl>cl)t+=' · naik ke level '+r.lvl+'!'}
  else if(r.wait)t='(xp tidak bertambah, terlalu cepat)';
  else t='(belum ada xp)';
  if(r.lvl){cl=r.lvl;$('lvn').textContent=r.lvl;$('lvb').style.width=Math.round(r.cur/r.need*100)+'%';$('lvx').textContent=r.cur+' / '+r.need+' xp'}
  cb(t)},function(){cb('')})}

/* tab */
var tb=document.querySelectorAll('#gt button'),gm=document.querySelectorAll('.gm');
tb.forEach(function(b,i){b.onclick=function(){tb.forEach(function(x){x.classList.remove('on')});gm.forEach(function(x){x.classList.remove('on')});b.classList.add('on');gm[i].classList.add('on')}});

/* 1. puzzle */
var LV={easy:{n:3,mx:9,m:5},medium:{n:4,mx:15,m:7},hard:{n:5,mx:25,m:10}},lv='easy',pz,pw={easy:0,medium:0,hard:0};
function pnew(){
 var c=LV[lv],nums=[R(2,c.mx)],ops=[],v=nums[0],i,o,b,k,d;
 for(i=1;i<c.n;i++){
  o=OPS[R(0,3)];
  if(o=='−'){if(v<1)o='+';else b=R(1,Math.min(c.mx,v))}
  if(o=='×'){b=R(2,c.m);if(v*b>400)o='+'}
  if(o=='÷'){d=[];for(k=2;k<=c.m+2;k++)if(v>0&&v%k===0)d.push(k);if(d.length)b=d[R(0,d.length-1)];else o='+'}
  if(o=='+')b=R(1,c.mx);
  v=ap(v,o,b);nums.push(b);ops.push(o)}
 pz={nums:nums,t:v,s:ops.map(function(){return -1})};prender();$('pm').textContent='';$('ps').textContent='benar ('+lv+'): '+pw[lv]}
function prender(){
 var h='';pz.nums.forEach(function(n,i){h+='<span class=nm>'+n+'</span>';if(i<pz.s.length)h+='<button type=button class=sl data-i='+i+'>'+(pz.s[i]<0?'?':OPS[pz.s[i]])+'</button>'});
 h+='<span>=</span><span class=nm><b>'+pz.t+'</b></span>';$('pq').innerHTML=h;
 $('pq').querySelectorAll('.sl').forEach(function(b){b.onclick=function(){if(pz.ok)return;var i=+b.dataset.i;pz.s[i]=(pz.s[i]+1)%4;prender();$('pm').textContent=''}})}
$('pc').onclick=function(){
 if(pz.ok)return;
 if(pz.s.indexOf(-1)>-1){$('pm').textContent='isi semua tanda dulu';return}
 var r=pz.nums[0],i;for(i=0;i<pz.s.length;i++){r=ap(r,OPS[pz.s[i]],pz.nums[i+1]);if(r!==r)break}
 if(r!==r)$('pm').textContent='ada pembagian yang hasilnya bukan bilangan bulat';
 else if(r===pz.t){
  pz.ok=1;pw[lv]++;$('ps').textContent='benar ('+lv+'): '+pw[lv];$('pm').textContent='benar! menghitung xp...';
  var cur=pz;
  award('puzzle_'+lv,0,function(t){
   if(pz===cur)$('pm').textContent='benar! '+t+' soal baru sebentar lagi...';
   setTimeout(function(){if(pz===cur)pnew()},1200)})}
 else $('pm').textContent='belum tepat, hasilmu '+r+'. coba lagi'};
$('pn').onclick=pnew;
document.querySelectorAll('#pl button').forEach(function(b){b.onclick=function(){document.querySelectorAll('#pl button').forEach(function(x){x.classList.remove('on')});b.classList.add('on');lv=b.dataset.l;pnew()}});
pnew();

/* 2. kuis */
var QN=10,qi=0,qs=0,qst=0;
function qshow(){
 if(qi>=QN){
  $('qp').textContent='selesai';
  $('qb').innerHTML='<div class=n style="margin:1rem 0">skor: '+qs+'/'+QN+'</div><p id=qx class=gmsg>menghitung xp...</p><button id=qr>main lagi</button>';
  $('qr').onclick=function(){qi=qs=qst=0;qshow()};
  award('kuis',qs,function(t){var e=$('qx');if(e)e.textContent=t});
  return}
 var x=gq(),set=[x.r],w,k=1,t=0;
 while(set.length<4&&t++<200){w=x.r+R(-10,10);if(w<0||set.indexOf(w)>-1)continue;set.push(w)}
 while(set.length<4){w=x.r+11*k++;if(set.indexOf(w)<0)set.push(w)}
 set.sort(function(){return Math.random()-.5});
 $('qp').textContent='soal '+(qi+1)+'/'+QN+' · skor '+qs+' · beruntun '+qst;
 var h='<div class="n big" style="margin:1rem 0">'+x.t+' = ?</div><div class=ans>';
 set.forEach(function(v){h+='<button data-v='+v+'>'+v+'</button>'});
 $('qb').innerHTML=h+'</div><div id=qf class=gmsg></div>';
 var bs=$('qb').querySelectorAll('.ans button');
 bs.forEach(function(b){b.onclick=function(){
  var ok=+b.dataset.v===x.r;
  bs.forEach(function(z){z.disabled=true;if(+z.dataset.v===x.r)z.classList.add('ok')});
  if(ok){qs++;qst++}else{b.classList.add('no');qst=0}
  $('qp').textContent='soal '+(qi+1)+'/'+QN+' · skor '+qs+' · beruntun '+qst;
  $('qf').innerHTML=(ok?'benar! ':'salah, jawabannya '+x.r+'. ')+'<button id=qn>'+(qi+1>=QN?'lihat skor':'lanjut')+'</button>';
  $('qn').onclick=function(){qi++;qshow()}}})}
qshow();

/* 3. labirin */
var N=7,W=2*N+1,mz,px,py,mv,pd,done,mid=0;
function mnew(){
 var g=[],x,y;for(y=0;y<W;y++){g.push([]);for(x=0;x<W;x++)g[y].push(1)}
 var st=[[1,1]];g[1][1]=0;
 while(st.length){
  var c=st[st.length-1],nb=[];
  [[0,-2],[2,0],[0,2],[-2,0]].forEach(function(d){var nx=c[0]+d[0],ny=c[1]+d[1];if(nx>0&&ny>0&&nx<W-1&&ny<W-1&&g[ny][nx]==1)nb.push(d)});
  if(!nb.length){st.pop();continue}
  var d=nb[R(0,nb.length-1)];g[c[1]+d[1]/2][c[0]+d[0]/2]=0;g[c[1]+d[1]][c[0]+d[0]]=0;st.push([c[0]+d[0],c[1]+d[1]])}
 var key=function(x,y){return y*W+x},pr={},q=[[1,1]];pr[key(1,1)]=-1;
 while(q.length){
  var c2=q.shift();if(c2[0]==W-2&&c2[1]==W-2)break;
  [[1,0],[-1,0],[0,1],[0,-1]].forEach(function(d){var nx=c2[0]+d[0],ny=c2[1]+d[1];if(g[ny][nx]===0&&!(key(nx,ny) in pr)){pr[key(nx,ny)]=key(c2[0],c2[1]);q.push([nx,ny])}})}
 var path=[],k=key(W-2,W-2),onp={};
 while(k!=-1){path.unshift(k);onp[k]=1;k=pr[k]}
 [.25,.5,.75].forEach(function(f){var kk=path[Math.floor(path.length*f)];g[Math.floor(kk/W)][kk%W]=2});
 var extra=0,tries=0;
 while(extra<2&&tries++<200){var ex=R(1,W-2),ey=R(1,W-2);if(g[ey][ex]===0&&!onp[key(ex,ey)]){g[ey][ex]=2;extra++}}
 g[W-2][W-2]=3;
 mid++;mz=g;px=1;py=1;mv=0;pd=null;done=false;$('dq').classList.remove('on');$('mm').textContent='';mdraw()}
function mdraw(){
 var m=$('mz'),h='',x,y,v,c;m.style.setProperty('--w',W);
 for(y=0;y<W;y++)for(x=0;x<W;x++){v=mz[y][x];c=v==1?'w':v==2?'d':v==3?'e':'f';if(x==px&&y==py)c+=' p';h+='<i class="'+c+'">'+(v==2?'?':'')+'</i>'}
 m.innerHTML=h}
function mask(x,y){
 var o=gq();pd={x:x,y:y,r:o.r};$('dt').textContent=o.t+' =';$('da').value='';$('dq').classList.add('on');$('da').focus()}
function mmove(dx,dy){
 if(done)return;var nx=px+dx,ny=py+dy,v=mz[ny][nx];if(v==1)return;
 if(v==2){$('mm').textContent='pintu terkunci!';mask(nx,ny);return}
 px=nx;py=ny;mv++;pd=null;$('dq').classList.remove('on');$('mm').textContent='';
 if(v==3){
  done=true;var my=mid,steps=mv;
  $('mm').textContent='kamu berhasil keluar! '+steps+' langkah. menghitung xp...';
  award('labirin',0,function(t){if(my===mid)$('mm').textContent='kamu berhasil keluar! '+steps+' langkah '+t})}
 mdraw()}
function mopen(){
 if(!pd)return;
 if(parseInt($('da').value,10)===pd.r){mz[pd.y][pd.x]=0;var d=pd;pd=null;$('dq').classList.remove('on');$('mm').textContent='pintu terbuka!';px=d.x;py=d.y;mv++;mdraw()}
 else{$('mm').textContent='salah, coba soal lain';mask(pd.x,pd.y)}}
$('db').onclick=mopen;
$('da').onkeydown=function(e){if(e.key=='Enter'){e.preventDefault();mopen()}};
$('mr').onclick=mnew;
document.querySelectorAll('.dp button').forEach(function(b){b.onclick=function(){var d=b.dataset.d.split(',');mmove(+d[0],+d[1])}});
document.addEventListener('keydown',function(e){
 if(mb.style.display=='none'||!gm[2].classList.contains('on')||document.activeElement==$('da'))return;
 var d={ArrowUp:[0,-1],ArrowDown:[0,1],ArrowLeft:[-1,0],ArrowRight:[1,0],w:[0,-1],s:[0,1],a:[-1,0],d:[1,0]}[e.key];
 if(d){e.preventDefault();mmove(d[0],d[1])}});
mnew();
})();
 </script>
<?php } elseif($p=='catatan'){ ?>
 <h1>catatan</h1><div class=c><form method=post><?=f()?><input type=hidden name=a value=catatan><label>judul</label><input name=judul required><label>isi</label><textarea name=isi rows=3 required></textarea>
 <label>warna</label><select name=warna><option value=0>kuning</option><option value=1>biru</option><option value=2>ungu</option><option value=3>hijau</option></select><button>simpan</button></form></div>
 <div class=notes><?php foreach(q("SELECT * FROM catatan WHERE user_id=? ORDER BY id DESC",[$u['id']]) as $r)echo"<div class='nt w{$r['warna']}'><div class=row><h3>".h($r['judul'])."</h3>".$del('catatan',$r['id'])."</div><p>".h($r['isi'])."</p><small>".date('d/m/Y H:i',strtotime($r['tgl']))."</small></div>"; ?></div>
<?php } elseif($p=='tugas'){ echo"<h1>tugas</h1>"; ?>
 <div class=c><h3>lampiran saya</h3><span class=mut>tambah foto atau tulisan untuk tugasmu</span>
 <div class=ibs>
 <form method=post enctype=multipart/form-data style=display:none><?=f()?><input type=hidden name=a value=foto><input type=file name=foto id=fi accept="image/*" onchange="this.form.submit()"></form>
 <button type=button class=ib onclick="document.getElementById('fi').click()" aria-label="tambah foto"><svg viewBox='0 0 12 9'><path d='M3 0h4v1h-4zM0 1h12v8h-12z'/><path style='fill:var(--card)' d='M4 3h4v4h-4z'/><path d='M5 4h2v2h-2z'/></svg><span>foto</span></button>
 <button type=button class=ib onclick="document.getElementById('tm').classList.add('on')" aria-label="tambah teks"><svg viewBox='0 0 9 9'><path d='M1 7h2v1h-2zM2 6h2v1h-2zM3 5h2v1h-2zM4 4h2v1h-2zM5 3h2v1h-2zM6 2h2v1h-2zM7 1h2v1h-2zM0 8h2v1h-2zM8 0h1v1h-1z'/></svg><span>teks</span></button></div>
 <div class=notes><?php $any=0;foreach(q("SELECT * FROM lampiran WHERE user_id=? ORDER BY id DESC",[$u['id']]) as $r){$any=1;$tg=date('d/m/Y H:i',strtotime($r['tgl']));
  if($r['tipe']=='foto')echo"<div class='nt w1'><img class=lp src='uploads/".h($r['isi'])."' alt='foto'><div class=row><small>$tg</small>".$del('lampiran',$r['id'])."</div></div>";
  else echo"<div class='nt w0'><p>".h($r['isi'])."</p><div class=row><small>$tg</small>".$del('lampiran',$r['id'])."</div></div>";}
  if(!$any)echo"<p class=mut>belum ada lampiran</p>"; ?></div></div>
 <div class=mod id=tm onclick="if(event.target==this)this.classList.remove('on')"><div class="c mc" style="text-align:left"><form method=post><?=f()?><input type=hidden name=a value=teks><label>tulisan</label><textarea name=isi rows=4 required></textarea>
 <div class=row style="justify-content:flex-end"><button type=button class=o onclick="document.getElementById('tm').classList.remove('on')">batal</button><button>simpan</button></div></form></div></div>
<?php
 if($adm)echo"<div class=c><form method=post>".f()."<input type=hidden name=a value=tugas><label>judul</label><input name=judul required><label>deskripsi</label><textarea name=isi rows=2></textarea><label>tenggat</label><input type=date name=deadline required><button>buat tugas</button></form></div>";
 foreach(q("SELECT t.*,(SELECT 1 FROM selesai s WHERE s.tugas_id=t.id AND s.user_id=?) d FROM tugas t ORDER BY deadline",[$u['id']]) as $r){$late=$r['deadline']<date('Y-m-d')&&!$r['d'];
  echo"<div class=c style='".($r['d']?'opacity:.6':'')."'><div class=row><div><h3>".h($r['judul'])."</h3><span class=mut>tenggat ".date('d/m/Y',strtotime($r['deadline'])).($late?' (terlambat)':'')."</span><p>".h($r['isi'])."</p></div><div>";
  if(!$adm)echo"<form method=post>".f()."<input type=hidden name=a value=centang><input type=hidden name=id value={$r['id']}><button class='".($r['d']?'o':'')."'>".($r['d']?'batal':'selesai')."</button></form>";
  if($adm)echo$del('tugas',$r['id']);echo"</div></div></div>";}
} elseif($p=='nilai'){ echo"<h1>nilai</h1><div class=c><form method=post>".f()."<input type=hidden name=a value=nilai>";
 if($adm){echo"<label>siswa</label><select name=uid>";foreach(q("SELECT id,nama FROM users WHERE role='siswa'") as $r)echo"<option value={$r['id']}>".h($r['nama'])."</option>";echo"</select>";}
 echo"<div class=g style='margin:0'><div><label>mapel</label><input name=mapel required></div><div><label>nilai</label><input type=number name=skor min=0 max=100 required></div></div><button>tambah</button></form></div>";
 $rows=q($adm?"SELECT n.*,u.nama FROM nilai n JOIN users u ON u.id=n.user_id ORDER BY u.nama,n.mapel":"SELECT n.*,'' nama FROM nilai n WHERE user_id=? ORDER BY mapel",$adm?[]:[$u['id']])->fetchAll();
 if($rows){$av=round(array_sum(array_column($rows,'skor'))/count($rows),1);echo"<div class=c><div class=row><h3>tabel nilai</h3><span>rata rata $av</span></div><table><tr>".($adm?"<th>siswa</th>":"")."<th>mapel</th><th>nilai</th><th style='width:30%'></th><th></th></tr>";
  foreach($rows as $r)echo"<tr>".($adm?"<td>".h($r['nama'])."</td>":"")."<td>".h($r['mapel'])."</td><td>{$r['skor']}</td><td><div class=bar style='margin:0'><i style='width:{$r['skor']}%'></i></div></td><td>".$del('nilai',$r['id'])."</td></tr>";echo"</table></div>";}
} elseif($p=='musik'){ ?>
 <h1>musik</h1>
 <div class=c>
  <span class=mut>seret kaset ke piringan, lalu klik kasetnya untuk memutar. klik lagi untuk jeda. seret kaset keluar piringan untuk melepasnya.</span>
  <div class=stage id=st>
   <div class=tt><div class=disc id=dc></div><div class=arm id=arm></div></div>
   <div class=rack id=rk>rak kaset</div>
   <div class=ks id=ks tabindex=0 role=button aria-label="kaset enchanted - taylor swift"><div class=kl><b>enchanted</b><small>taylor swift</small></div><div class=kr><i class=rl></i><i class=rl></i></div></div>
  </div>
  <p id=ms class=mut style="text-align:center;min-height:1.4rem">kaset ada di rak.</p>
  <div style="max-width:300px;margin:0 auto"><label>volume</label><input type=range id=vl min=0 max=100 value=70></div>
 </div>
 <script>
(function(){
var $=function(i){return document.getElementById(i)},ks=$('ks'),st=$('st'),dc=$('dc'),rk=$('rk'),arm=$('arm'),ms=$('ms'),
 on=false,pl=false,drag=false,mvd=false,sx,sy,ox,oy,au=new Audio('Taylor Swift - Enchanted.mp3');
au.volume=.7;
function say(t){ms.textContent=t}
function ui(){dc.classList.toggle('sp',pl);ks.classList.toggle('pl',pl);arm.classList.toggle('on',pl)}
function stop(){au.pause();pl=false;ui()}
function put(el,snap){
 var a=st.getBoundingClientRect(),b=el.getBoundingClientRect();
 ks.classList.toggle('snap',!!snap);
 ks.style.left=(b.left-a.left+b.width/2-ks.offsetWidth/2)+'px';
 ks.style.top=(b.top-a.top+b.height/2-ks.offsetHeight/2)+'px'}
function lay(){put(on?dc:rk)}
function seat(){on=true;put(dc,1);say('kaset sudah di piringan. klik kaset untuk memutar.')}
function play(){
 if(!on){say('letakkan kaset di piringan dulu (seret kasetnya).');return}
 if(pl){stop();say('dijeda. klik kaset untuk lanjut.');return}
 var p=au.play();
 if(p&&p.then)p.then(function(){pl=true;ui();say('memutar: enchanted - taylor swift')}).catch(fail);
 else{pl=true;ui()}}
function fail(){stop();say('gagal memutar. pastikan file enchanted.mp3 ada di folder yang sama dengan index.php.')}
au.onended=function(){pl=false;ui();say('selesai. klik kaset untuk memutar lagi.')};
au.onerror=fail;
ks.onpointerdown=function(e){
 e.preventDefault();ks.setPointerCapture(e.pointerId);drag=true;mvd=false;sx=e.clientX;sy=e.clientY;ox=ks.offsetLeft;oy=ks.offsetTop;
 ks.classList.remove('snap');ks.classList.add('grab')};
ks.onpointermove=function(e){
 if(!drag)return;var dx=e.clientX-sx,dy=e.clientY-sy;
 if(Math.abs(dx)+Math.abs(dy)>5)mvd=true;
 if(mvd){ks.style.left=(ox+dx)+'px';ks.style.top=(oy+dy)+'px'}};
ks.onpointerup=function(e){
 if(!drag)return;drag=false;ks.classList.remove('grab');
 if(!mvd){play();return}
 var k=ks.getBoundingClientRect(),d=dc.getBoundingClientRect();
 if(Math.hypot(k.left+k.width/2-d.left-d.width/2,k.top+k.height/2-d.top-d.height/2)<d.width/2)seat();
 else{if(on)stop();on=false;put(rk,1);say('kaset dikembalikan ke rak.')}};
ks.onpointercancel=function(){drag=false;ks.classList.remove('grab');lay()};
ks.onkeydown=function(e){if(e.key=='Enter'||e.key==' '){e.preventDefault();if(!on)seat();else play()}};
$('vl').oninput=function(){au.volume=this.value/100};
window.addEventListener('resize',lay);window.addEventListener('load',lay);lay();
})();
 </script>
<?php } elseif($p=='profil'){ ?>
 <h1>profil</h1><form method=post class=col2><?=f()?><input type=hidden name=a value=profil>
 <div class=c><label>username</label><input value="<?=h($u['username'])?>" disabled><label>nama</label><input name=nama value="<?=h($u['nama'])?>" required>
 <label>kelas</label><input name=kelas value="<?=h($u['kelas'])?>"><label>bio</label><textarea name=bio rows=3><?=h($u['bio'])?></textarea>
 <label>sandi baru</label><div class=pw><input type=password name=password autocomplete=new-password><?=eye()?></div><button>simpan</button></div>
 <div class="c ph2"><div id=pvw><?=av($u,'xl')?></div><h3 style="margin-top:1rem"><?=h($u['nama'])?></h3><span class=mut>level <?=$lvl?></span>
 <input type=hidden name=pp id=ppv value="<?=h($u['foto'])?>">
 <div style="margin-top:1.2rem"><button type=button onclick="document.getElementById('ppm').classList.add('on')">pilih profil</button></div></div></form>
 <div class=mod id=ppm onclick="if(event.target==this)this.classList.remove('on')"><div class="c mc"><h3>pilih profil</h3>
 <div class=pick><?php for($i=1;$i<=5;$i++)echo"<button type=button class='ppb".($u['foto']=="pp$i.png"?' sel':'')."' onclick=\"ppp('pp$i.png',this)\"><img src='pp$i.png' alt='pp $i'></button>"; ?></div>
 <span class=mut>jangan lupa tekan simpan setelah memilih</span><div style="margin-top:1rem"><button type=button class=o onclick="document.getElementById('ppm').classList.remove('on')">tutup</button></div></div></div>
 <script>function ppp(v,b){document.getElementById('ppv').value=v;document.getElementById('pvw').innerHTML='<img class="av xl" src="'+v+'" alt="">';document.querySelectorAll('.ppb').forEach(function(x){x.classList.remove('sel')});b.classList.add('sel');document.getElementById('ppm').classList.remove('on')}</script>
<?php } elseif($p=='admin'&&$adm){ echo"<h1>siswa</h1><div class=c><table><tr><th></th><th>nama</th><th>username</th><th>kelas</th><th>xp</th><th></th></tr>";
 foreach(q("SELECT * FROM users ORDER BY role,nama") as $r)echo"<tr><td>".av($r)."</td><td>".h($r['nama'])."</td><td>".h($r['username'])."</td><td>".h($r['kelas'])."</td><td>{$r['xp']}</td><td>".($r['role']=='admin'?'admin':$del('users',$r['id']))."</td></tr>";echo"</table></div>";
} else echo"<h1>halaman tidak ada</h1>"; ?>
</main></div>
<div class=mod id=dm onclick="if(event.target==this)this.classList.remove('on')"><div class="c mc"><img src=kucing2.gif alt="" style="height:120px;image-rendering:pixelated" onerror="this.style.display='none'"><h3 style="margin:1rem 0">apakah kamu yakin menghapus ini?</h3><button type=button id=dy>ya</button> <button type=button class=o onclick="document.getElementById('dm').classList.remove('on')">tidak</button></div></div>
<script>var dfm;function dx(b){dfm=b.form;document.getElementById('dm').classList.add('on')}document.getElementById('dy').onclick=function(){if(dfm)dfm.submit()};document.addEventListener('keydown',function(e){if(e.key=='Escape')document.querySelectorAll('.mod.on').forEach(function(m){m.classList.remove('on')})})</script>
<?php endif ?>
<script>
(function(){var m="url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 7 6' shape-rendering='crispEdges'%3E%3Cpath d='M1 0h2v1h-2zM4 0h2v1h-2zM0 1h7v2h-7zM1 3h5v1h-5zM2 4h3v1h-3zM3 5h1v1h-1z'/%3E%3C/svg%3E\")",cs=['#9cc6fa','#b7d6ff','#8bbcf8','#cfe3ff'];
function hh(){var e=document.createElement('i');e.className='hh';var s=14+Math.floor(Math.random()*3)*7;e.style.cssText='left:'+Math.random()*98+'vw;--s:'+s+'px;--c:'+cs[Math.floor(Math.random()*4)]+';--m:'+m+';--t:'+(7+Math.random()*6)+'s;--x:'+(Math.random()*80-40)+'px';document.body.appendChild(e);setTimeout(function(){e.remove()},14000)}
setInterval(hh,900);for(var i=0;i<5;i++)setTimeout(hh,i*300)})();
</script></body></html>