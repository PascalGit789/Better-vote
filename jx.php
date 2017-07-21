<?php session_start();
require_once('includes/connexionBD.php');
include('includes/script_general.php');

function ajax_return($messageRetour){
	if(!isset($messageRetour)) print("Erreur avec la fonction");
	elseif(is_array($messageRetour)) echo json_encode($messageRetour);
	elseif($messageRetour === true) print($messageRetour == true?1:0);
	else print($messageRetour);
	exit;
}

if(isset($_GET["action"])){
	$action = $_GET["action"];
	$messageRetour = 1;
	
	if($action == "create_poll"){
		$question = isset($_POST['question'])?$_POST['question']:"";
		$options = isset($_POST['options'])?json_decode($_POST['options'], true):[];
		$public = isset($_POST['public'])?$_POST['public']:1;
		$is_restricted_ip = isset($_POST['is_restricted_ip'])?$_POST['is_restricted_ip']:1;
		
		if($public) $url = get_url();
		else $url = get_private_url();
		
		$error = 0; // Validation PHP
		if(empty($question) || count($options) < 2) $error = 1;
		
		if($error == 0){
			$question = ucfirst(substr($question, 0, 100)); 
			
			$ip = $_SERVER['REMOTE_ADDR'];
			if(array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
				$ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
				$ip = $ip[0];
			}
				
			$stmt = $PDO->prepare("SELECT count(id) AS nbr_poll FROM poll WHERE address_ip = :ip");
			$stmt->execute(['ip' => $ip]);
			$count_poll = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if($count_poll["nbr_poll"] < 1000){
				$stmt = $PDO->prepare('INSERT INTO poll (question, public, one_vote_ip, url, address_ip, datetime_creation) VALUES (:question, :public, :one_vote_ip, :url, :ip, "'.date("Y-m-d H:i:s").'")');
				$stmt->execute(['question' => $question, 'public' => $public, 'one_vote_ip' => $is_restricted_ip, 'url' => $url, 'ip' => $ip]);
				$id_poll = $PDO->lastInsertId();
				
				
				$stmt = $PDO->prepare('INSERT INTO poll_option (id_poll, texte) VALUES (:id_poll, :texte)');
				
				foreach($options AS $key => $option){
					if($key > 9) continue; // PHP validation, block after 10 poll options
					$option = ucfirst(substr($option, 0, 75)); 
					$stmt->execute(['id_poll' => $id_poll, 'texte' => $option]);
				}
				
				$messageRetour = $url;
			}else $messageRetour = 2;
		}else $messageRetour = 0;
	}
	elseif($action == "vote"){
		if(!isset($_POST['id_poll'])){
			ajax_return(0);
		}
		$id_poll = $_POST['id_poll'];
		$vote_order = isset($_POST['vote_order'])?json_decode($_POST['vote_order'], true):[];
		
		$ip = $_SERVER['REMOTE_ADDR'];
		if(array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
			$ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = $ip[0];
		}
		
		// Verification if one vote per ip
		$stmt = $PDO->prepare("SELECT one_vote_ip FROM poll WHERE id = :id_poll");
		$stmt->execute(['id_poll' => $id_poll]);
		$info_poll = $stmt->fetch(PDO::FETCH_ASSOC);
		
		$already_voted = 0;
		if($info_poll["one_vote_ip"] == 1){ // Check if poll only accept one vote per ip
			$stmt = $PDO->prepare("SELECT id FROM voter WHERE address_ip = :ip AND id_poll = :id_poll");
			$stmt->execute(['ip' => $ip, 'id_poll' => $id_poll]);
			$info_voter = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if(isset($info_voter["id"]) && $info_voter["id"] > 0){ // Check if voter already voted
				$already_voted = 1;
				$messageRetour = 2;
			}
		}
		
		if($already_voted == 0 && count($vote_order) > 0){
			$stmt = $PDO->prepare('INSERT INTO voter (address_ip, id_poll, datetime_vote) VALUES (:ip, :id_poll, "'.date("Y-m-d H:i:s").'")');
			$stmt->execute(['ip' => $ip, 'id_poll' => $id_poll]);
			$id_voter = $PDO->lastInsertId();
			
			$stmt = $PDO->prepare('INSERT INTO poll_vote (id_poll_option, vote_order, id_voter) VALUES (:id_poll_option, :vote_order, :id_voter)');
			
			foreach($vote_order AS $vote_order => $id_poll_option){
				$stmt->execute(['id_poll_option' => $id_poll_option, 'vote_order' => $vote_order, 'id_voter' => $id_voter]);
			}
			
			$_SESSION["just_voted"] = 1;
		}
		
	}
	elseif($action == "refresh_result"){
		if(!isset($_POST['url_poll'])){
			ajax_return(0);
		}
		$messageRetour = show_result($_POST['url_poll'], 1);
	}
	elseif($action == "get_more_list"){
		if(!isset($_POST['public_list'])){
			ajax_return(0);
		}
		$public_list = $_POST['public_list'];
		$list_size = isset($_POST['list_size'])?$_POST['list_size']:0;
		
		
		if($public_list == "most_voted"){
			$most_voted_poll = $PDO->prepare("SELECT v.id_poll, COUNT(v.id) FROM voter AS v LEFT JOIN poll AS p ON v.id_poll = p.id WHERE p.public = 1 GROUP BY v.id_poll ORDER BY count(v.id) DESC LIMIT :list_size, 10 ");
			$most_voted_poll->execute(['list_size' => $list_size]);
			$most_voted_poll = $most_voted_poll->fetchAll();
			
			$where_poll = [];
			$where_poll_in = "";
			foreach($most_voted_poll AS $info){
				$where_poll[] = $info["id_poll"];
				$where_poll_in .= "?, ";
			}
			$where_poll_in = rtrim($where_poll_in, ', ');
			
			if($where_poll_in > ""){
				$most_voted = $PDO->prepare("SELECT id, question, url FROM poll WHERE id IN (".$where_poll_in.")");
				$most_voted->execute($where_poll);
				$most_voted = $most_voted->fetchAll();
			}else $most_voted = [];
			
			$most_voted_formated = [];
			foreach($most_voted AS $info){
				$most_voted_formated[$info["id"]]["question"] = $info["question"];
				$most_voted_formated[$info["id"]]["url"] = $info["url"];
			}
			
			$messageRetour = "";
			foreach($where_poll AS $id_poll){
				if(isset($most_voted_formated[$id_poll])){
					$messageRetour .= show_poll_for_list($most_voted_formated[$id_poll]);  
				}
			}
			
			if(count($where_poll) == 0) $messageRetour = 0;
			
		}
		elseif($public_list == "trending"){
			// Get first X polls
			$poll_question = $PDO->prepare("SELECT id, question, url FROM poll WHERE public = 1 ORDER BY id DESC LIMIT 300");
			$poll_question->execute();
			$poll_question_list = $poll_question->fetchAll();
			
			// Get their options to count their number of votes
			$poll_info = [];
			$where_poll = [];
			$where_poll_in = "";
			foreach($poll_question_list AS $info){
				$id_poll = $info["id"];
				
				$where_poll[] = $id_poll;
				$where_poll_in .= "?, ";
				
				$poll_info[$id_poll]["question"] = $info["question"];
				$poll_info[$id_poll]["url"] = $info["url"];
			}
			$where_poll_in = rtrim($where_poll_in, ', ');
			
			if($where_poll_in > ""){
				$poll_option = $PDO->prepare("SELECT p.id, pv.id_poll_option, count(pv.id_voter) AS nbr_vote FROM poll AS p LEFT JOIN poll_option AS po ON p.id = po.id_poll LEFT JOIN poll_vote AS pv ON po.id = pv.id_poll_option WHERE p.id IN (".$where_poll_in.") AND vote_order = 1 GROUP BY pv.id_poll_option");
				$poll_option->execute($where_poll);
				$poll_option_list = $poll_option->fetchAll();
			}else $poll_option_list = [];
			
			// Count vote for each id poll
			$poll_vote = [];
			foreach($poll_option_list AS $info){
				if(!isset($poll_vote[$info["id"]])) $poll_vote[$info["id"]] = 0;
				$poll_vote[$info["id"]] += $info["nbr_vote"];
			}
			arsort($poll_vote, false);
			
			// Keep best 10
			$poll_trending = [];
			$inc_trending = 0;
			$inc_size = 0;
			foreach($poll_vote AS $id_poll => $info){
				$inc_size++;
				if($inc_size <= $list_size) continue;
				$poll_trending[$id_poll]["question"] = $poll_info[$id_poll]["question"];
				$poll_trending[$id_poll]["url"] = $poll_info[$id_poll]["url"];
				$inc_trending++;
				if($inc_trending == 10) break;
			}
			
			$messageRetour = "";
			foreach($poll_trending AS $poll_info){
				$messageRetour .= show_poll_for_list($poll_info);  
			}
			if(count($poll_trending) == 0) $messageRetour = 0;
			
		}
		elseif($public_list == "new"){
			$poll_question = $PDO->prepare("SELECT question, url FROM poll WHERE public = 1 ORDER BY id DESC LIMIT :list_size, 10 ");
			$poll_question->execute(['list_size' => $list_size]);
			$poll_question_list = $poll_question->fetchAll();
			
			$messageRetour = "";
			foreach($poll_question_list AS $poll_info){
				$messageRetour .= show_poll_for_list($poll_info);  
			}
			
			if(count($poll_question_list) == 0) $messageRetour = 0;
		}	
	}
	elseif($action == "search"){
		if(!isset($_POST['search_text'])){
			ajax_return(0);
		}
		$search_text = "%".$_POST['search_text']."%";
		
		$poll_question = $PDO->prepare("SELECT question, url FROM poll WHERE question LIKE ? AND public = 1 ORDER BY datetime_creation DESC");
		$poll_question->execute([$search_text]);
		$poll_question_list = $poll_question->fetchAll();
		
		$messageRetour = "";
		$messageRetour .= "<div class='div_main_border div_main_border_search col-xs-10 col-xs-offset-1 col-md-65-pourcent col-md-offset-17-5-pourcent'>";
		$messageRetour .= "<h3>SEARCH RESULTS</h3>";
			$messageRetour .= "<div class='clear'></div>";
			if(count($poll_question_list) > 0){
				$messageRetour .= "<ol>";
					foreach($poll_question_list AS $poll_info){
						$messageRetour .= show_poll_for_list($poll_info);  
					}
				$messageRetour .= "</ol>";
			}else{
				$messageRetour .= "<p style='font-size:1.25rem; margin-top:20px; '>No result</p>";
			}
		$messageRetour .= "<div>";
	}
	
	ajax_return($messageRetour);
}

?>