<?php
require_once(dirname(__FILE__).'/config.php');

if(!isset($_SERVER['argv'][1]) || !isset($_SERVER['argv'][2]))
    die("You must defined username and password and run like this: php run.php user pass\n");
if(!is_file('/tmp/dlinkmotion.state'))
    file_put_contents('/tmp/dlinkmotion.state','0');

$camUser = $_SERVER['argv'][1];
$camPass = $_SERVER['argv'][2];

$leases = getActiveLeases();

$motion = true;
foreach($leases as $lease){
    if( in_array($lease['mac'],$mac_list)){
        //ping to check if device is in range of wifi and connected. 
        if(ping($lease['ip']))
            $motion = false;
    }
}

if($motion != file_get_contents('/tmp/dlinkmotion.state')){
    //echo "state has changed since last run";
    if(!setMotion($cam,$camUser,$camPass,$motion,$sensitivity))
        echo "camera login failed\n";
    else
        file_put_contents('/tmp/dlinkmotion.state',$motion);
}

function setMotion($camhost,$user,$pass,$motion,$sensitivity=90){/*{{{*/
    $ch = curl_init();

    if($motion)
        $params = "ReplySuccessPage=motion.htm&ReplyErrorPage=motion.htm&MotionDetectionEnable=1&MotionDetectionScheduleDay=0&MotionDetectionScheduleMode=0&MotionDetectionSensitivity=$sensitivity&ConfigSystemMotion=Save";
    else
        $params = "ReplySuccessPage=motion.htm&ReplyErrorPage=motion.htm&MotionDetectionEnable=0&MotionDetectionScheduleDay=0&ConfigSystemMotion=Save";

    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_URL, "http://$camhost/setSystemMotion");
    $out = curl_exec($ch);
    $status = curl_getinfo($ch,CURLINFO_HTTP_CODE);

    curl_close($ch);
    if($status != 200)
        return false;
    return true;
}/*}}}*/
function ping($host) {/*{{{*/
    $res = exec('ping -c2 -W0.5 '.$host,$out);
    //print_r( $out);
    foreach($out as $line){
        if(stristr($line,'100% packet loss'))
            return false;
    }
    return true;
}/*}}}*/
function getActiveLeases(){/*{{{*/
    $leases = array();
    $content = file_get_contents('/var/lib/dhcp/dhcpd.leases');
    $content = explode("\n",$content);
    unset($content[0]);
    unset($content[1]);
    unset($content[2]);
    $content = implode("\n",$content);
    preg_match_all('#lease (.*)}#Umsi',$content,$result);
    $data = $result[1];
    foreach($data as $key=>$line){
        $tmp = explode('{',$line);
        $ip = trim($tmp[0]);
        $leases[$ip]['ip'] = $ip;
        $line = $tmp[1];
        $lease = explode(';',$line);
        foreach($lease as $l){
            $l = trim($l);
            if(substr($l,0,6) == 'starts'){
                $l = substr($l,9);
                $leases[$ip]['starts'] = date("Y-m-d H:i:s",strtotime($l));
            }
            if(substr($l,0,4) == 'ends'){
                $l = substr($l,7);
                $leases[$ip]['ends'] = date("Y-m-d H:i:s",strtotime($l));
            }
            if(substr($l,0,7) == 'binding'){
                $l = substr($l,7);
                $leases[$ip]['state'] = substr($l,7);
            }
            if(substr($l,0,15) == 'client-hostname'){
                $l = substr($l,7);
                preg_match('#"(.*)"#Umsi',$l,$res);
                $leases[$ip]['hostname'] = $res[1];
            }
            if(substr($l,0,8) == 'hardware'){
                $leases[$ip]['mac'] = substr($l,18);
            }
        }

    }
    foreach($leases as $lease){
    }
    return $leases;
}/*}}}*/
?>
