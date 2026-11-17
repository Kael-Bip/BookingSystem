<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond($data, int $code=200): never {http_response_code($code);echo json_encode($data);exit;}
function fail(string $message,int $code=400): never {respond(['error'=>$message],$code);}
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required',405);
if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) fail('JSON required',415);
$body=json_decode(file_get_contents('php://input'),true);if(!is_array($body)) fail('Invalid JSON');
$action=$body['action']??'';
try {
$c=require __DIR__.'/config.php';
$db=new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function query($sql,$args=[]){global $db;$s=$db->prepare($sql);$s->execute($args);return $s;}
function user(){if(empty($_SESSION['uid'])) fail('Please sign in.',401);return $_SESSION['uid'];}
function account($id){return query('SELECT name,email FROM users WHERE id=?',[$id])->fetch();}
function booking($ref,$uid){$b=query("SELECT b.reference,u.email,b.service_id AS serviceId,s.name AS serviceName,b.booking_date AS date,TIME_FORMAT(b.booking_time,'%H:%i') AS time,b.quantity,b.total,b.status,b.payment_method AS paymentMethod FROM bookings b JOIN users u ON u.id=b.user_id JOIN services s ON s.id=b.service_id WHERE b.reference=? AND b.user_id=?",[$ref,$uid])->fetch();if(!$b)fail('Booking not found',404);return $b;}
if($action==='services') respond(query('SELECT * FROM services ORDER BY id')->fetchAll());
if($action==='session') respond(['user'=>isset($_SESSION['uid'])?account($_SESSION['uid']):null]);
if($action==='logout'){$_SESSION=[];session_destroy();respond(['ok'=>true]);}
if(in_array($action,['register','login'],true)){
$email=strtolower(trim((string)($body['email']??'')));$password=(string)($body['password']??'');
if($action==='register'){$name=trim((string)($body['name']??''));if(!$name||strlen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||strlen($password)<6||strlen($password)>72)fail('Enter a valid name, email and password (6–72 characters).');
if(query('SELECT id FROM users WHERE email=?',[$email])->fetch())fail('Email already registered.',409);
query('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$db->lastInsertId();}
else{$u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();if(!$u||!password_verify($password,$u['password_hash']))fail('Invalid email or password.',401);$id=$u['id'];}
session_regenerate_id(true);$_SESSION['uid']=$id;respond(['user'=>account($id)]);}
$uid=user();
if($action==='list'){$refs=query('SELECT reference FROM bookings WHERE user_id=? ORDER BY created_at DESC,reference DESC',[$uid])->fetchAll();respond(array_map(fn($r)=>booking($r['reference'],$uid),$refs));}
if($action==='create'){$sid=filter_var($body['serviceId']??null,FILTER_VALIDATE_INT);$qty=filter_var($body['quantity']??null,FILTER_VALIDATE_INT);$date=(string)($body['date']??'');$time=(string)($body['time']??'');date_default_timezone_set('Asia/Manila');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date||$date<date('Y-m-d')||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time)||!$qty||$qty<1||$qty>10)fail('Invalid booking details.');$s=query('SELECT * FROM services WHERE id=?',[$sid])->fetch();if(!$s)fail('Invalid room.');$ref='NES-'.strtoupper(bin2hex(random_bytes(8)));query('INSERT INTO bookings(reference,user_id,service_id,booking_date,booking_time,quantity,total) VALUES(?,?,?,?,?,?,?)',[$ref,$uid,$sid,$date,$time,$qty,$s['price']*$qty]);respond(booking($ref,$uid));}
if($action==='pay'){$ref=(string)($body['reference']??'');$method=$body['method']??'';$status=$body['status']??'';if(!in_array($method,['Demo Wallet','Pay at Property'],true)||!in_array($status,['Confirmed','Payment pending','Payment failed'],true))fail('Invalid demo payment.');$db->beginTransaction();$b=query('SELECT * FROM bookings WHERE reference=? AND user_id=? FOR UPDATE',[$ref,$uid])->fetch();if(!$b)fail('Booking not found',404);if($b['status']==='Confirmed')fail('Booking already confirmed.',409);query('UPDATE bookings SET status=?,payment_method=? WHERE reference=?',[$status,$method,$ref]);query('INSERT INTO payments(reference,method,status,amount) VALUES(?,?,?,?)',[$ref,$method,$status,$b['total']]);$db->commit();respond(booking($ref,$uid));}
fail('Unknown action',404);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log($e->getMessage());fail('Database or server error. Check Laragon and database configuration.',500);}
