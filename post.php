<?php
require_once('lib/ReceiptMailDecoder.class.php');
require_once('HTTP/Request.php');
include_once 'lib/spyc.php';

$conf = Spyc::YAMLLoad( '/usr/local/sfw/gyazo-mail/conf.yml' );

    //-- debug print start
	print "---------------------------\n";
	print date(DATE_RFC822)."\n";
    //-- debug print end
     
    $stdin = fopen("php://stdin", "r");
     
    if($stdin)
    {
		$body = '';
        while (!feof($stdin)){
            $body .= fgets($stdin,1024);
        }
 
        $decoder =& new ReceiptMailDecoder($body);
 
        $toAddr   = $decoder->getToAddr();
        $fromAddr = $decoder->getFromAddr();
        $toString = $decoder->getDecodedHeader( 'to' );
        $subject  = $decoder->getDecodedHeader( 'subject' );
 
        // text/planなメール本文を取得する
        $body = mb_convert_encoding($decoder->body['text'],"sjis","jis");
        // text/htmlなメール本文を取得する
        $body = mb_convert_encoding($decoder->body['html'],"sjis","jis");
 
        $account = substr($toAddr, 0, strpos($toAddr, "@"));

        //-- debug print start
        print $toAddr."\n";
        print $fromAddr."\n";
        print $toString."\n";
        print $subject."\n";
        print $body."\n";
        //-- debug print end

		//送信先メールアドレスから設定をロードする。
		if( !isset( $conf[ $account ] ) ){
			print "no match account setting.\n";
			exit;
		}

		//送信元メールアドレスリストに存在するかチェックする。
		if( !in_array( $fromAddr, $conf[ $account ][ 'sender' ] ) ){
			print "no match sender address.\n";
			exit;
		}
	
		$tempFiles = array();
		if( $decoder->isMultipart() ) {
			 $num_of_attaches = $decoder->getNumOfAttach();
			 for ( $i=0 ; $i < $num_of_attaches ; ++$i ) {
			 	$fpath = tempnam( _TEMP_ATTACH_FILE_DIR_, "todoattach_" );
				if ( $decoder->saveAttachFile( $i, $fpath ) ) {
					$tempFiles["$fpath"] = $decoder->attachments[$i]['mime_type'];
				}
			}
		}else{
			print "file not found.\n";
			exit;
		}
 
        //-- debug print start
		print count( $tempFiles )."\n";
        //-- debug print end

		//正しく画像ファイルが来ているかを確認する。
		if( !count( $tempFiles ) ){
			print "file not found.\n";
			exit;
		}else{
			$url = 'http://'.$conf[ $account ][ 'upload_server' ].$conf[ $account ][ 'upload_path' ];
			$response = Array();

			foreach( $tempFiles as $fpath => $mime_type ){
				//画像を投げる
				$req =& new HTTP_Request($url);
				$req->setMethod(HTTP_REQUEST_METHOD_POST);
				$req->addFile("imagedata", $fpath, "Content-Type: ".$mime_type);
				if( isset( $conf[ $account ][ 'use_auth' ]) ){
					$req->setBasicAuth( $conf[ $account ][ 'use_auth' ][ 'auth_id' ] , $conf[ $account ][ 'use_auth' ][ 'auth_pw' ] );
				}

				$req->sendRequest();				
				$response[] = $req->getResponseBody();
			}
		}

        //-- debug print start
		var_dump($response);
        //-- debug print end

		$receiver = $fromAddr;
		$mailform = '';

		if( isset( $conf[ $account ][ 'receiver' ] ) ){
			if( isset( $conf[ $account ][ 'receiver' ][ 'mail' ] ) ){
				$receiver = $conf[ $account ][ 'receiver' ][ 'mail' ];
			}
			if( isset( $conf[ $account ][ 'receiver' ][ 'facebook' ] ) ){
				$receiver = $conf[ $account ][ 'receiver' ][ 'facebook' ];
				$mailfrom = "From:".$conf['facebook-sender'];
			}
		}

        //-- debug print start
		print 'send to '.$receiver."\n";
        //-- debug print end

		//帰ってきたURLを、送信元メールアドレスに送信する
		if( mb_send_mail( $receiver, $conf['subject'], join( $response,"\n"), $mailfrom ) ){
			print "success send mail\n";
		}else{
			print "faled  send mail\n";
		}
    }
