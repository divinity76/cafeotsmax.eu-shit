<?php 
require_once('hhb_.inc.php');
require_once('hhb_datatypes.inc.php');
require_once('LoginWithPHP.php');
init();
//$socket=LogInAndReturnSocketHandle(89999999,"autoaccount","Rxst Ymstptmoi");while(1){sleep(3);};
class CreateCharacterException extends Exception{};
class CharacterNameAlreadyExistsException extends CreateCharacterException{};
class TooManyCharactersOnAccountException extends CreateCharacterException{};
class IllegalCharacterNameException extends CreateCharacterException{};
class CreateAccountException extends Exception{};
class DuplicateEmailException extends CreateAccountException{};
class DuplicateAccountNameException extends CreateAccountException{};
class AccountNameDoesNotExistInDatabaseException extends Exception{};
$accountnumber_min=89999999;
$accountnumber_max=99999999;
$password="autoaccount";

//createAccounts($accountnumber_min,$accountnumber_max,$password);return;
//var_dump(getRandomAccountsWithFreeCharacterSlots());return;
//var_dump(getFreeCharacterSlotsOnAccount('39582939'));return;
//var_dump(getRandomLegalCharacterName(),GetRandomLegalCharacterName(),GetRandomLegalCharacterName());return;
//var_dump(createRandomCharactersOnRandomAccounts());return;
//var_dump(robCharacter(89999999,'autoaccount','Rxst Ymstptmoi'));return;
var_dump(robRandomCharacter());return;




function robRandomCharacter(){
	global $dbc;
	$stm=$dbc->prepare('SELECT `accounts`.`accountname` AS `accountame`, `accounts`.`password` AS `password`, `characters`.`charactername` AS `charactername` FROM `accounts` INNER JOIN `characters` ON `accounts`.`id` = `characters`.`accounts_id` WHERE `characters`.`is_robbed` = 0');
	$stm->execute();
	//var_dump($stm->fetchAll(PDO::FETCH_ASSOC));
	$acc=$stm->fetch(PDO::FETCH_ASSOC);
	if($acc===false){
		throw new Exception("All characters in db are robbed already!");
		}
	return robCharacter($acc['accountname'],$acc['password'],$acc['charactername']);
	}

function robCharacter($accountname,$password,$charactername){
	$characterNameExistsInDb=doesCharacterNameExistInDb($charactername);
	if($characterNameExistsInDb){
		if(isCharacterRobbed($charactername)){
			throw new Exception($charactername." is already robbed!");
			}
	}
	$socket=LogInAndReturnSocketHandle($accountname,$password,$charactername);
	$packets=array();
	$packets[]=hex2bin('0A0082FFFF030000330B0000');//open backpack.
	$packets[]=hex2bin('0F0078FFFF400005550C05AA04F4010701');//throw manarune on floor hidden V
	$packets[]=hex2bin('0F0078FFFF4000025A0C02AC04F0010701');//throw UH rune on floor hidden >
	$packets[]=hex2bin('0F0078FFFF400001EC0B01A204F0010701');//throw life ring hidden <<
	$packets[]=hex2bin('0F0078FFFF020000F10B00AA04EC010701');//throw AOL ^
	$packets[]=hex2bin('0F0078FFFF030000330B00A804F2010701');//throw backpack 1 step to south.
	$packets[]=hex2bin('010014');//explicit logout packet (although close socket also works..);
	$sentTotal=0;
	foreach($packets as $packet){
	//echo "sending: ".bin2hex($packet).PHP_EOL.PHP_EOL;
	$sentTotal+=$sent=socket_write($socket,$packet,strlen($packet));
	usleep(80000);
	//^not sure why we need to sleep, but if we send too fast, some packets never arrive :S
	//usleep(50000);
	}
	global $dbc;
	$stm=$dbc->prepare('UPDATE `characters` SET `is_robbed` = 1 WHERE `charactername` = ?');
	$stm->execute(array($charactername));
	return true;
}

function isCharacterRobbed($charactername){
	if(!doesCharacterNameExistInDb($charactername)){
		return false;//far as we know, nope.
		}
	global $dbc;
	$stm=$dbc->prepare("SELECT `is_robbed` FROM `characters` WHERE `charactername` = ?");
	$stm->execute(array($charactername));
	$res=$stm->fetch(PDO::FETCH_ASSOC);
	$res=!!(int)$res['is_robbed'];
	return $res;
}
function createRandomCharactersOnRandomAccounts(){
	global $dbc;
	$charactersCreated=0;
	$accounts=getRandomAccountsWithFreeCharacterSlots();
	foreach($accounts as $account){
		$i=0;
		$freeCharacterSlots=getFreeCharacterSlotsOnAccount($account['accountname']);
		for($i=0;$i<$freeCharacterSlots;++$i){
			$characterName=getRandomLegalCharacterName();
			createCharacter($account['accountname'],$account['password'],$characterName);
			++$charactersCreated;
			echo "created character \"".$characterName."\" on account ".$account['accountname']." ..total: ".$charactersCreated.PHP_EOL;
		}
	}
	return $charactersCreated;
}


function createAccounts($accountnumber_min,$accountnumber_max,$password){
	global $dbc;
//test random name generation: for($i=0;$i<100;++$i){echo LegalizeCharacterName(hash('sha512',$i,true)).PHP_EOL;};return;
$stm=$dbc->prepare('SELECT COUNT(*) AS `res` FROM `accounts` WHERE `accountname` = ?');
	for($i=$accountnumber_min;$i<$accountnumber_max;++$i){
	$stm->execute(array($i));
	$res=$stm->fetch(PDO::FETCH_ASSOC);
	if($res['res']!=='0'){
		continue;}
	createAccount($i,$password,'fake'.$i.'@email.com');
	echo "created account ".$i.PHP_EOL;;
}
echo "done creating accounts! returning..";
return;
}
echo "created character! :D";



function getRandomLegalCharacterName/*VeryUnlikelyToBeTaken*/(){
	global $dbc;
	$stm=$dbc->query('select MAX(`id`) AS `res` FROM `characters` WHERE 1');
	$res=$stm->fetch(PDO::FETCH_ASSOC);
	$res=(int)$res['res'];
	++$res;
	$ret=LegalizeCharacterName(hash('sha512',$res,true));
	return $ret;
}
function LegalizeCharacterName($name){
	$legalCharacters="qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM ";	
	$removeTriplets=function(&$name){//Name can't contain 3 same letters one by one. Good: Moonster Wrong: Mooonster
		$name_arr=str_split($name);
		$h1=false;
		$h2=false;
		$ret=false;
		$newname="";
		foreach($name_arr as $character){
			if(strcasecmp($h2,$character) === 0 && strcasecmp($h2,$character)===0){
				continue;//tripple.
				$ret=true;
			}
			$h2=$h1;
			$h1=$character;
			$newname.=$character;
			}
			$name=$newname;
			return $ret;
		};
		$removeIllegalContents=function(&$name){
			$counts=0;
			$ret=false;
			////gamemaster,game master,game-master,game'master,fuck,sux,suck,noob,tutor
			$name=str_replace("gamemaster","gxmemxster",$name,$counts);
			if($counts!==0){$ret=true;}
			$name=str_replace("game master","xame xaster",$name,$counts);
			if($counts!==0){$ret=true;}
			$name=str_replace("game-master","gxme-masxer",$name,$counts);
			if($counts!==0){$ret=true;}
			$name=str_replace("game'master","gaxe'master",$name,$counts);
			if($counts!==0){$ret=true;}
			$name=str_replace("fuck","xuxk",$name,$counts);
			if($counts!==0){$ret=true;}
			$name=str_replace("sux","xux",$name,$counts);
			if($counts!==0){$ret=true;}
			$name=str_replace("suck","xuck",$name,$counts);
			if($counts!==0){$ret=true;}
			$name=str_replace("noob","boob",$name,$counts);
			if($counts!==0){$ret=true;}
			$name=str_replace("tutor","tltlr",$name,$counts);
			if($counts!==0){$ret=true;}
			return $ret;
			};
		$makeSureSpaceContentHasSufficientLength=function(&$name) use($legalCharacters){
			$ret=false;
			$name_arr=explode(" ",$name);
			foreach($name_arr as &$chunk){
					if(strlen($chunk)<2){
						$chunk.=strtolower(str_replace(" ","x",createRandomString($legalCharacters,2)));
						$ret=true;
						};
				}
				unset($chunk);
				$name=implode(" ",$name_arr);
				return $ret;
			};
		$makeSureCaseIsCorrect=function(&$name){
			$ret=false;
			$originalName=$name;
			$name_arr=explode(" ",$name);
			foreach($name_arr as &$chunk){
					$chunk=strtolower(trim($chunk));
					$chunk[0]=strtoupper($chunk[0]);
				}
			unset($chunk);
			$name=implode(" ",$name_arr);
			if(strcmp($name,$originalName)===0){
				return false;
				}
			return true;
			};
		$makeSureNameIsNotTooLong=function(&$name){
			if(strlen($name)>25){
				$name=trim(substr($name,0,20));
				return true;
				}
				return false;
			};
			
	$name=trim($name);
	while(false!==strpos($name,"  ")){
		$name=str_replace("  "," ",$name);
		}
	$name_arr=str_split($name);
	$censored_name="";
	$i=0;
	$h1=false;
	$h2=false;
	foreach($name_arr as $character){

		if(false===strpos($legalCharacters,$character)){
		continue;//illegal character
		}
		if($h1===$character && $h2===$character){
		continue;
		}
		$h2=$h1;
		$h1=$character;
		$censored_name.=$character;
}
		$name=$censored_name;
		unset($censored_name,$character,$h1,$h2,$name_arr);
		$illegal_starts=array("gm","cm","god","tutor");
		$found=true;
		while($found!==false){
			$found=false;
			foreach($illegal_starts as $illegal_start){
				if(strtolower(substr($name,0,strlen($illegal_start)))==strtolower($illegal_start)){
					$found=true;
					$name=substr($name,strlen($illegal_start));
					}
				}
		}
		unset($found,$illegal_starts,$illegal_start);
		$name=trim($name);
		if(strlen($name)<1){
		$name=str_replace(" ","",createRandomString($legalCharacters,4));
			//return false;/*failed.*/
			}
		$name=strtolower(trim($name));
		while(false!==strpos($name,"  ")){
			$name=str_replace("  "," ",$name);
		}
		$name_arr=explode(" ",$name);
		foreach($name_arr as &$part){
			if(strlen($part)<2){
					$part.=str_replace(" ","",createRandomString($legalCharacters,4));
				}
			$part=strtolower($part);
			$part[0]=strtoupper($part[0]);
			}
		$name=implode(" ",$name_arr);
		unset($part,$name_arr);
		while($makeSureCaseIsCorrect($name) || $removeTriplets($name) || $removeIllegalContents($name) || $makeSureSpaceContentHasSufficientLength($name) || $makeSureNameIsNotTooLong($name)){/**/};
		//there are more rules, but I won't bother being comprehensive.. except this 1 part.
		if(strlen($name)>25){
			$name=trim(substr($name,0,25));
			while(true){
		    $charactersBeforeSpace=0;
			for($i=strlen($name);$i>1;--$i){
				if($name[$i-1]===" "){
					break;
					}
					++$charactersBeforeSpace;
				}
				if($charactersBeforeSpace<3){
				$name=substr($name,0,max($charactersBeforeSpace,1)*-1);
				} else {
				break;
				}
				//now its possible that the name is empty i think... whatever, i give up. let the bugreports flow
		}
		}
		while($makeSureCaseIsCorrect($name) || $removeTriplets($name) || $removeIllegalContents($name) || $makeSureSpaceContentHasSufficientLength($name) || $makeSureNameIsNotTooLong($name)){/**/};
		//potential (but highly unlikely) infinite loop with makeSureSpaceContentHasSufficientLength and makeSureNameIsNotTooLong ...
		return $name;
}
function doesAccountNameExistInDb($accountname){
	global $dbc;
	$stm=$dbc->prepare('SELECT COUNT(*) AS `res` FROM `accounts` WHERE `accountname` = ?');
	$stm->execute(array($accountname));
	$res=$stm->fetch(PDO::FETCH_ASSOC);
	$res=!!(int)$res['res'];
	return $res;
}
function doesCharacterNameExistInDb($charactername){
	global $dbc;
	$stm=$dbc->prepare('SELECT COUNT(*) AS `res` FROM `characters` WHERE `charactername` = ?');
	$stm->execute(array($charactername));
	$res=$stm->fetch(PDO::FETCH_ASSOC);
	$res=!!(int)$res['res'];
	return $res;
}
function getFreeCharacterSlotsOnAccount($accountname){
	if(!doesAccountNameExistInDb($accountname)){
		throw new AccountNameDoesNotExistInDatabaseException($accountname);
	}
	global $dbc;
	$stm=$dbc->prepare('SELECT COUNT(*) AS `res` FROM `characters` WHERE `characters`.`accounts_id` = (SELECT `id` FROM `accounts` WHERE accountname = ? LIMIT 1) AND `characters`.`accounts_id`<> 0;');
	$stm->execute(array($accountname));
	$res=$stm->fetch(PDO::FETCH_ASSOC);
	$res=$res['res'];
	return 5-$res;
}
function getRandomAccountsWithFreeCharacterSlots(){
	global $dbc;
	$query='SELECT `accountname`,`password` FROM `accounts` LEFT JOIN `characters` ON `characters`.`accounts_id` = `accounts`.`id` GROUP BY `accounts`.`id` HAVING count(1) < 5;';
	$stm=$dbc->query($query);
	$res=$stm->fetchAll(PDO::FETCH_ASSOC);
	unset($stm);
	return $res;
	//suggestion from IRC:
	//SELECT `accountname`,`password` FROM `accounts` LEFT JOIN `characters` ON `characters`.`accounts_id` = `accounts`.`id` GROUP BY `accounts`.`id` HAVING count(1) < 5;
	
	}
function createAccount($acc,$password,$email){
	$ch=hhb_curl_init();
	hhb_curl_exec($ch,'http://cafeotsmax.eu/?subtopic=createaccount');//grab session ID/cookies/etc
	//http://cafeotsmax.eu/index.php?subtopic=createaccount&action=saveaccount
	/*
reg_name:1729762
reg_email:email@foo.com
reg_password:password
reg_password2:password
rulesServer:true
rules:true
I Agree.x:26
I Agree.y:15
		*/
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,array(
		'reg_name'=>$acc,
		'reg_email'=>$email,
		'reg_password'=>$password,
		'reg_password2'=>$password,
		'rulesServer'=>"true",
		'rules'=>"true",
		'I Agree.x'=>rand(1,100),//???
		'I Agree.y'=>rand(1,30),//???
		));
		$html=hhb_curl_exec($ch,'http://cafeotsmax.eu/index.php?subtopic=createaccount&action=saveaccount');
		curl_close($ch);
		unset($ch);
		if(false!==stripos($html,'Account with this e-mail address already exist in database')){
			throw new DuplicateEmailException($email);
			}
		if(false!==stripos($html,'Account with this name already exist')){
			throw new DuplicateAccountNameException($acc);
			}
		if(false===stripos($html,'Your account has been created. Please write down the account number and password')){
		echo "ERROR: Expected html with 'Your account has been created. Please write down the account number and password', BUT GOT: ";
		var_dump(base64_encode($html));
			throw new CreateAccountException();
			}
		global $dbc;
		$stm=$dbc->prepare('INSERT INTO `accounts` (`accountname`,`password`,`email`,`notes`) VALUES(?,?,?,?)');
		$stm->execute(array($acc,$password,$email,'autocreated.'));
		return true;
}
function createCharacter($acc,$pass,$charactername){
	$ch=hhb_curl_init();
	hhb_curl_exec($ch,'http://cafeotsmax.eu/index.php?subtopic=accountmanagement');//grabing a session..
	curl_setopt($ch,CURLOPT_POST,true);
	curl_setopt($ch,CURLOPT_POSTFIELDS,array(
	'account_login'=>$acc,
	'password_login'=>$pass,
	'Submit.x'=>rand(1,100),//??
	'Submit.y'=>rand(1,30),//??
	));
	$html=hhb_curl_exec($ch,'http://cafeotsmax.eu/index.php?subtopic=accountmanagement');
	assert(false!==stripos($html,'Welcome in your account!'));
	unset($html);
	//now we're logged in!
//http://cafeotsmax.eu/index.php?subtopic=accountmanagement&action=createcharacter
/*
	Create Character.x:93
Create Character.y:1
YES: WITH A SPACE!!
	*/
	curl_setopt($ch,CURLOPT_POST,true);
	curl_setopt($ch,CURLOPT_POSTFIELDS,array(
	'Create Character.x'=>rand(1,100),
	'Create Character.y'=>rand(1,30),
	));
	$html=hhb_curl_exec($ch,'http://cafeotsmax.eu/index.php?subtopic=accountmanagement&action=createcharacter');
	assert(false!==stripos($html,'Please choose a name, vocation and sex for your character.'));
	unset($html);
	//http://cafeotsmax.eu/index.php?subtopic=accountmanagement&action=createcharacter
	/*
savecharacter:1
newcharname:testnamea
newcharsex:1
newcharvocation:1
Submit.x:68
Submit.y:13
Name	
*/
$postfields=array(
	'savecharacter'=>1,
	'newcharname'=>$charactername,
	'newcharsex'=>rand(0,1),//female=0/male=1
	'newcharvocation'=>array(1,3,4)[rand(0,2)],//mage=1/paladin=3/knight=4 (what happened to 2? no idea..)
	'Submit.x'=>rand(1,100),//??
	'Submit.y'=>rand(1,30),//??
	);
	curl_setopt($ch,CURLOPT_POST,true);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$postfields);
	$html=hhb_curl_exec($ch,'http://cafeotsmax.eu/index.php?subtopic=accountmanagement&action=createcharacter');
	if(false!==stripos($html,'This name is already used. Please choose another name')){
			throw new CharacterNameAlreadyExistsException($charactername);
		}
	//You have too many characters on your account
		if(false!==stripos($html,'You have too many characters on your account')){
			throw new TooManyCharactersOnAccountException($acc);
		}
		if(false!==stripos($html,'This name contains invalid letters, words or format')){
			throw new IllegalCharacterNameException($charactername);
			}
	if(false===stripos($html,"has been created.")){
		echo "ERROR in createCharacter! expected HTML with \"has been created\", but got..";
		var_dump(base64_encode($html),'postfields:',$postfields);
		throw new CreateCharacterException();
	}
	unset($html);
	curl_close($ch);
	unset($ch);
	global $dbc;
	$stm=$dbc->prepare('INSERT INTO `characters` (`accounts_id`,`charactername`,`is_robbed`,`notes`) VALUES(
	(SELECT `id` FROM `accounts` WHERE `accountname` = ?),?,?,?
	);');
	$stm->execute(array($acc,$charactername,0,'autocreated'));
	return true;
}

function init(){
	hhb_init();
	$db_path=hhb_combine_filepaths(__DIR__,'characters.sqlite3db');
	$GLOBALS['db_path']=$db_path;
	if(!file_exists($db_path)){
		createDatabase($db_path);
	} else {
	//echo "THE DATABASE EXISTED ALREADY.";
	}
	$dbc=new PDO('sqlite:'.$db_path,'','',array(
	PDO::ATTR_EMULATE_PREPARES => false, 
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	));
	$GLOBALS['dbc']=$dbc;
	return true;
}
function createRandomString($characters, $length){
        $output = '';
		
        for($i = 0; $i < $length; $i++) {
                $output .= $characters[rand(1,strlen($characters)-1)];
        }
        return $output;
}

function createDatabase($db_path){
	$rc=fopen($db_path,'w+b');
	assert($rc!==false);
	assert(fclose($rc));
	unset($rc);
	$dbc=new PDO('sqlite:'.$db_path,'','',array(
	PDO::ATTR_EMULATE_PREPARES => false, 
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	));
	$dbc->query('DROP TABLE IF EXISTS `accounts`;');
	$dbc->query('CREATE TABLE `accounts` (
  `id` INTEGER,
  `accountname` VARCHAR(255) NOT NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL DEFAULT NULL,
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id` ASC)
);');
$dbc->query('DROP TABLE IF EXISTS `characters`;');
$dbc->query('CREATE TABLE `characters` (
  `id` INTEGER,
  `accounts_id` INT(255) NOT NULL DEFAULT NULL,
  `charactername` VARCHAR(255) NOT NULL DEFAULT NULL,
  `is_robbed` BOOLEAN(1) DEFAULT 0,
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id` ASC)
);');
$dbc->query('SELECT * FROM `accounts`');
echo "CREATED THE DATABASE!";
unset($dbc);
return true;
}
