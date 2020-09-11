<?php

$ADDR=	    "10.0.1.210"; // Local Pool Address

$MAC=		"SPA32:01:f0:f0:22:ca"; 				   // needs to be sniffed from mobile app in.touch2
$ID=		"IOS126ced49-32bb-4799-cdae-8af1f02237c0"; // needs to be sniffed from mobile app in.touch2

$msgBonjr=		"<BONJR>$ID</BONJR>";
$msgStart=		"<PACKT><SRCCN>$ID</SRCCN><DESCN>$MAC</DESCN><DATAS>AVERS\x02</DATAS></PACKT>";
$msgGetStatus = "<PACKT><SRCCN>$ID</SRCCN><DESCN>$MAC</DESCN><DATAS>STATU\x01\x00\x00\x02\x7d</DATAS></PACKT>";
$msgSetTemp =	"<PACKT><SRCCN>$ID</SRCCN><DESCN>$MAC</DESCN><DATAS>SPACK\x00\x06\x07\x46\x06\x06\x00\x0fTT</DATAS></PACKT>";

function send_UDP($msg)
{
	global $sock,$ADDR;
	socket_sendto($sock, $msg, strlen($msg) , 0 , $ADDR , 10022);
}

function GetBetween($content,$start,$end)
{
    $r = explode($start, $content);
    if (isset($r[1])){
        $r = explode($end, $r[1]);
        return $r[0];
    }
    return '';
}

//Create a UDP socket
if(!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
{
	$errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);
    
    die("Couldn't create socket: [$errorcode] $errormsg ");
}

send_UDP($msgBonjr);
send_UDP($msgStart);

if ($temperature = str_replace(",", ".", $_GET["T"]))
{
   
   send_UDP(str_replace("TT",pack("H*", "0".dechex($temperature*18)), $msgSetTemp)); 
   $r = socket_recv($sock, $buf, 255,MSG_WAITALL);
   echo "Temperature set to ".$temperature."°C // 0x0".dechex($temperature*18)."</br>";
}

else
{
	send_UDP($msgGetStatus);
	for ($i = 0; $i <= 17; $i++)
	{
		$r = socket_recv($sock, $buf, 4096,MSG_WAITALL);
		$bytes=unpack('C*', GetBetween($buf,'<DATAS>STATV','</DATAS>'));
		if (sizeof($bytes)==0) continue;
		if ($bytes[1]===6)
		{
			// Actual Temperature: $bytes[36]
			if ($bytes[36]) $currtemp= ($bytes[36]+512/($bytes[36] % 3))/18; else $currtemp=0;
			echo "ActTemp: ".$currtemp."°C </br>";
		}
		if ($bytes[1]===7)
		{
			// Set Temperature: $bytes[4]
			$setpoint=($bytes[4]+512/($bytes[4] % 3))/18;
			echo "SetTemp:".$setpoint."°C</br>";
			
			// Pumps: $bytes[6] x80= Pump 1 / x20= Pump 2 / x08= Pump 3 / x04= Pump 4
			if (($bytes[6]&0x80)===0x80) echo "Pump1:1</br>"; else echo "Pump1:0</br>";
			if (($bytes[6]&0x20)===0x20) echo "Pump2:1</br>"; else echo "Pump2:0</br>";
			if (($bytes[6]&0x08)===0x08) echo "Pump3:1</br>"; else echo "Pump3:0</br>";
			if (($bytes[6]&0x04)===0x04) echo "Pump4:1</br>"; else echo "Pump4:0</br>";
			
			// Light $bytes[37] - 0x03
			
			if ($bytes[37]===0x03) echo "Light:1</br>"; else echo "Light:0</br>";
			
			// Heater on $bytes[5] - 0xc8
			if (($bytes[5]&0xc8)===0xc8) echo "Heater:1</br>"; else echo "Heater:0</br>";
			
		}
		if ($bytes[1]===8)
		{
			echo "in.XM Version:".$bytes[22]."v".$bytes[23].".".$bytes[24]."</br>";
		}
		if ($bytes[1]===8)
		{
			echo "byte41:".hexdec($bytes[41])."</br>";
			
			if (($bytes[41]&0x02)===0x02) echo "ERROR_NOFLOW:1</br>"; else echo "ERROR_NOFLOW:0</br>";
		}
		if ($bytes[1]===9)
		{
			echo "byte8:".hexdec($bytes[8])."</br>";
		}
	}
	echo "timestamp:".(time()-1230768000+7200)."</br>";

}
socket_close($sock);

?>
