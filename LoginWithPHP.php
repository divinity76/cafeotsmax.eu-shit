<?php 
require_once('hhb_.inc.php');
require_once('hhb_datatypes.inc.php');
hhb_init();
function LogInAndReturnSocketHandle($accountNumber,$password,$characterName){
$socket=socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
if($socket===false){
	throw new Exception("socket_create() failed: reason: " . socket_strerror(socket_last_error()));
}
assert(socket_set_block ( $socket)===true);
assert(socket_bind($socket,0,mt_rand(1024,5000))!==false);//TODO: don't use mt_rand... it has a (small) chance choosing a used address..
if(socket_connect($socket,
'91.228.199.112',7171 //WARNING: DO NOT USE 112.ten.greendata.pl , while 91.228.199.112 reverse lookups to 112.ten.greendata.pl , 112.ten.greendata.pl RESOLVES to  91.228.196.112 ...notice the 199 vs 196
)!==true){
	   throw new Exception("socket_connect() failed: reason: " . socket_strerror(socket_last_error()));
}
//assert(socket_set_option($socket,getprotobyname('tcp'),SO_KEEPALIVE,1)===true);//TODO: confirm, is this really correct?
$packets=array();
$tmp=hex2bin('0A0200F80200');
//^ first 2 bytes is "character login protocol"
//1 of the bytes (not sure which) means OS (linux or windows or flash) 
//the remaining 3 bytes? no clue. but constant.
//echo "login packet start: ".strtoupper(bin2hex($tmp)).PHP_EOL;
$tmp.=to_little_uint32_t($accountNumber);
//echo "login packet + account number: ".strtoupper(bin2hex($tmp)).PHP_EOL;
$tmp.=to_little_uint16_t(strlen($characterName));
//echo "login packet + charactername header (len of name \"".$characterName."\": ".strlen($characterName)."): ".strtoupper(bin2hex($tmp)).PHP_EOL;
$tmp.=$characterName;
//echo "login packet + charactername: ".strtoupper(bin2hex($tmp)).PHP_EOL;
$tmp.=to_little_uint16_t(strlen($password));
//echo "login packet + password header: ".strtoupper(bin2hex($tmp)).PHP_EOL;
$tmp.=$password;
//echo "login packet + password: ".strtoupper(bin2hex($tmp)).PHP_EOL;
addTCPChecksum($tmp); 
//echo "login packet + TCP checksum: ".strtoupper(bin2hex($tmp)).PHP_EOL;
$packets[]=$tmp;
$packets[]=hex2bin("01001E");
#^send ping packet.
//now we should be logged in.
$replyBuf="";
$fullPacket="";
$sent=0;
$sentTotal=0;
foreach($packets as $packet){
//echo "sending: ".strtoupper(bin2hex($packet)).PHP_EOL.PHP_EOL;
$sentTotal+=$sent=socket_write($socket,$packet,strlen($packet));
usleep(80000);
//^not sure why we need to sleep, but if we send too fast, some packets never arrive :S
//usleep(50000);
}
//TODO: verify login successful...
return $socket;
//socket_close($socket);
}





function addTCPChecksum(&$data){
	$data=to_little_uint16_t(strlen($data)).$data;
}