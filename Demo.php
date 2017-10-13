<?php
#!/usr/bin/php5.6	
	@header('Content-type: text/html;charset=UTF-8');
	$start = date('Y-m-d 00:00:00', time());//当天开始时间
	$end= date('Y-m-d 23:59:59', time());//当天结束时间
	define("URL_PREFIX", "分页查询所有单据url");
	define("URL_PAY", "查询支付方式代码url");
	define("APP_ID", "APP_ID");
	define("APP_KEY", "APP_KEY");
	define("startTime", $start);
	define("endTime", $end);
	
	//分页查询所有单据
	function queryTickets(){
		$postBackParameter=null;
		$sendData = array(
			"appId"=>APP_ID,
			"startTime"=>startTime,
			"endTime"=>endTime
		);
	
		$queryNextPage = true;
		do{
			$sendData["postBackParameter"]=$postBackParameter;
			$json = doApiRequest(URL_PREFIX,$sendData);
			flush();
			//$result = json_decode($json,true,512,JSON_BIGINT_AS_STRING);
			$result = json_decode($json,true);
			if($result["status"] == "success"){
				$resultData = $result["data"];
				$postBackParameter = $resultData["postBackParameter"];
				$ticktes = $resultData["result"];
				processReturnTickets($ticktes);
				//是否进行下一页查询。
				$wantQuerySize = $resultData["pageSize"];
				$realQuerySize = count($ticktes);
				if($realQuerySize < $wantQuerySize) {
					$queryNextPage = false;
				}
				
				
			} else {
				break;
			}
		
		}while ($queryNextPage);
	}	
	queryTickets();
	
	//查询支付方式代码
	function findPayWay($payCode){
		$sendData = array(
			"appId"=>APP_ID
		);
		$json = doApiRequest(URL_PAY,$sendData);
		flush();
		$result = json_decode($json,true);
		//echo "<pre>";print_r($result);echo "<pre>";
		if($result["status"] == "success"){
			foreach ($result["data"] as $t){
				if($t["code"]==$payCode){
					return $t['name'];
				}
			}
		} else {
			break;
		}
	}
	
	//调用猎豹api接口
	function doApiRequest($url,$sendData){
		$jsondata = json_encode($sendData);
		$signature = strtoupper(md5(APP_KEY.$jsondata));
		return httpsRequest($url,$jsondata,$signature);
	}
	
	//提交数据函数
	function httpsRequest($url, $data,$signature){
		$time = time();
		$curl = curl_init();// 启动一个CURL会话
		// 设置HTTP头
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			"User-Agent: openApi",
			"Content-Type: application/json;charset=utf-8",
			"accept-encoding: gzip,deflate",
			"time-stamp: ".$time,
			"data-signature: ".$signature
		));
		curl_setopt($curl, CURLOPT_URL, $url);         // 要访问的地址
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);		// Post提交的数据包
		//curl_setopt($curl, CURLOPT_PROXY,'127.0.0.1:8888');//设置代理服务器,此处用的是fiddler，可以抓包分析发送与接收的数据
		curl_setopt($curl, CURLOPT_POST, 1);		// 发送一个常规的Post请求
	
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 获取的信息以文件流的形式返回
		$output = curl_exec($curl); // 执行操作
		if (curl_errno($curl)) {
		   echo 'Errno'.curl_error($curl);//捕抓异常
		}
		curl_close($curl); // 关闭CURL会话
		
		return $output; // 返回数据
	}
	
	//处理数据
	function processReturnTickets($ticktes){
		$dailyorders = array();
		//echo "<pre>";print_r($ticktes);echo "<pre>";
		foreach ($ticktes as $t=>$g){
			$dailyorders[$t]['Docno'] = 'S0'.substr($g["sn"],0,8).substr($g["sn"],-3);
			$dailyorders[$t]['Txdate'] = date('Y/m/d',strtotime($g["datetime"]));
			$dailyorders[$t]['Txtime'] = date('Hi',strtotime($g["datetime"]));
			$dailyorders[$t]['Storecode'] = '';//销售店铺号
			$dailyorders[$t]['Tillid'] = '';//收银机号码
			$dailyorders[$t]['Vipcode'] = '';//货号
			if($g["ticketType"]=='SELL' or $g["ticketType"]=='sell'){//ticketType单据类型：SELL销售单据, SELL_RETURN退货单据
				$dailyorders[$t]['OT'] = '';
				$dailyorders[$t]['Remarks'] = '';
				foreach ($g['payments'] as $k=>$v){
					if($v["code"] == "payCode_1"){//现金
						$dailyorders[$t]['CH'] = $v["amount"];
					}else if($v["code"] == "payCode_101"){//国内卡
						$dailyorders[$t]['BK'] = $v["amount"];
					}else if($v["code"] == "payCode_102"){//国外卡
						$dailyorders[$t]['BO'] = $v["amount"];
					}else if($v["code"] == "payCode_2"){//储值卡
						$dailyorders[$t]['CZ'] = $v["amount"];
					}else if($v["code"] == "payCode_103"){//礼券
						$dailyorders[$t]['LQ'] = $v["amount"];
					}else{//其它
						$dailyorders[$t]['OT'] = $dailyorders[$t]['OT']+$v["amount"];
						$dailyorders[$t]['Remarks'] .= findPayWay($v["code"]).$v["amount"];//备注
					}
				}
				$dailyorders[$t]['Ttldiscount'] = $g["totalAmount"]/($g["discount"]/100)*(1-$g["discount"]/100);//整单折扣
				$dailyorders[$t]['Netamt'] = $g["totalAmount"];//单据实收总额
			}else{
				foreach ($g['payments'] as $k=>$v){
					if($v["code"] == "payCode_1"){//现金
						$dailyorders[$t]['CH'] = '-'.$v["amount"];
					}else if($v["code"] == "payCode_101"){//国内卡
						$dailyorders[$t]['BK'] = '-'.$v["amount"];
					}else if($v["code"] == "payCode_102"){//国外卡
						$dailyorders[$t]['BO'] = '-'.$v["amount"];
					}else if($v["code"] == "payCode_2"){//储值卡
						$dailyorders[$t]['CZ'] = '-'.$v["amount"];
					}else if($v["code"] == "payCode_103"){//礼券
						$dailyorders[$t]['LQ'] = '-'.$v["amount"];
					}else{//其它
						$dailyorders[$t]['OT'] = '-'.$v["amount"];
						$dailyorders[$t]['Remarks'] = "123";//备注
					}
				}
				//$dailyorders[$t]['Oldcode'] = '00101759';
				$dailyorders[$t]['Ttldiscount'] = '-'.$g["totalAmount"]/($g["discount"]/100)*(1-$g["discount"]/100);//整单折扣
				$dailyorders[$t]['Netamt'] = '-'.$g["totalAmount"];//单据实收总额
			}		
			
		}
		insertOrders($dailyorders);
	}
	
	//插入数据库
	function insertOrders($dailyorders){
		$mysql_conf = array(
			'host'    => '', 
			'db'      => '', 
			'db_user' => '', 
			'db_pwd'  => '' 
		);
		$mysqli = @new mysqli($mysql_conf['host'], $mysql_conf['db_user'], $mysql_conf['db_pwd']);
		if ($mysqli->connect_errno) {
			die("could not connect to the database:\n" . $mysqli->connect_error);//诊断连接错误
		}
		$mysqli->query("set names 'utf8';");//编码转化
		//echo "<pre>";print_r($dailyorders);echo "<pre>";
		$select_db = $mysqli->select_db($mysql_conf['db']);
		if (!$select_db) {
			die("could not connect to the db:\n" .  $mysqli->error);
		}
		$sqltmp = "select max(Docno) as Docno from sales limit 0,1";
		$Docno = $mysqli->query($sqltmp)->fetch_object();
		$total = 0;
		$suctotal = 0;
		foreach($dailyorders as $t){
			$t1 ='(';
			$t2 ='(';
			if($t['Docno'] > $Docno->Docno){
				foreach($t as $k=>$v){
					$t1 .= $k.',';
					$t2 .= '\''.$v.'\''.',';
				}
				$t1 =rtrim($t1, ",") .')';
				$t2 =rtrim($t2, ",") .')';
				$sql = "insert into sales".$t1." VALUES".$t2;
				$res = $mysqli->query($sql);
				if (!$res) {
					die("sql error:\n" . $mysqli->error);
				}else{
					$suctotal = $suctotal+$res;
				}
				$total = $total+$res;
			}
		}
		echo '返回'.count($dailyorders).'条数据，新数据'.$total.'条，成功插入'.$suctotal.'条';
		$mysqli->close();
	}
	
?>
