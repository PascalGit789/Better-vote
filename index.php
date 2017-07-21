<?php 
require_once('includes/connexionBD.php');
include('includes/script_general.php');
safeSession();

$page = "homepage";
if(isset($_GET["vote"]) && strlen($_GET["vote"]) == 8){
	$vote = protect_xss($_GET["vote"]); 
	$page = "vote";
}

if(isset($_GET["result"]) && strlen($_GET["result"]) == 8){
	$result = protect_xss($_GET["result"]);
	$page = "result";
}

if(isset($_GET["page"]) && $_GET["page"] == "public") $page = "public";
if(isset($_GET["page"]) && $_GET["page"] == "about") $page = "about";
if(isset($_GET["page"]) && $_GET["page"] == "404") $page = "404";
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Better-Vote</title>
	
	<base href="/Better_vote/"> <!-- Just leave "/" In PROD      and "/Better_vote/" in DEV     CHERCHER TOUT LES /Better_vote        -->
	
	<link rel='shortcut icon' type='image/x-icon' href='favicon.ico' />
	<meta name="description" content="Create an interactive poll and get the results with multiple voting systems. Compare First-past-the-post's result to 5 other voting systems">
	
	<script src="http://code.jquery.com/jquery-1.11.0.min.js"></script>
	<?php if($page == "result"){ ?>
		<script src="includes/Chart.min.js"></script> 
		<script src="includes/Chart.PieceLabel.js"></script>
	<?php } ?>
	
	<!-- 
	<link rel="stylesheet" type="text/css" href="css/reset.css">
	<link rel="stylesheet" type="text/css" href="css/bootstrap.css">
	<link rel="stylesheet" type="text/css" href="css/style.css"> 
	-->
	
	<link rel="stylesheet" type="text/css" href="css/all_style.min.css">
</head>
<body>
<?php

$html_string = "";
$html_string .= show_header($page);

switch($page) {
    case "homepage":
        $html_string .= show_homepage();
        break;
		case "public":
				$html_string .= "<div class='container'>"; // The container is not inside the show_public_poll() function because show_public_poll() is used in the homepage which is already inside a container
					$html_string .= show_main_title("public");
					$html_string .= show_public_poll("public");
				$html_string .= "</div>";
        break;		
		case "vote":
        $html_string .= show_vote($vote);
        break;
		case "result":
        $html_string .= show_result($result, 0);
        break;
		case "about":
				$html_string .= show_about_page();
        break;
		case "404" :
				$html_string .= show_404_page("404");
				break;
    default:
        $html_string .= show_homepage();
}
echo $html_string;

function show_header($page){
	$html_string = "";
	
	$html_string .= "<div class='header container'>";
		$html_string .= "<div class='header_content'>";
		$html_string .= "<div class='col-xs-4 header_logo'><a href='./'><img src='includes/img/logo.png' alt='Better-Vote' width='250'></a></div>";
			$html_string .= "<div class='col-xs-4 col-md-8' style='float:right;'>";
				$html_string .= "<ul>";
					$html_string .= "<li class='".(isset($page)&&$page=='homepage'?'active':'')."'><a href='./'>Create Poll</a></li>";
					$html_string .= "<li class='".(isset($page)&&$page=='public'?'active':'')."'><a href='public'>Public Polls</a></li>";
					$html_string .= "<li class='".(isset($page)&&$page=='about'?'active':'')."'><a href='about'>About</a></li>";
				$html_string .= "</ul>";
			$html_string .= "</div>";
		$html_string .= "</div>";
	$html_string .= "</div>";

	return $html_string;
}

function show_homepage(){
	global $PDO;
	$html_string = "";
	$html_string .= "<div class='container'>";
		$html_string .= show_main_title("homepage");
		$html_string .= show_form();
	$html_string .= show_public_poll("homepage");
	$html_string .= "</div>";	
	return $html_string;
}

function show_form(){
	$ln = "<div class='clear' style='height:2px;'></div>";
	
	$html_string = "";
	$html_string .= "<!-- Credit css : http://codepen.io/arianalynn/pen/mPWdPZ -->";	
	$html_string .= "<form action='javascript:void(0);' class='form_create col-xs-10 col-xs-offset-1 col-md-8 col-md-offset-2 col-lg-6 col-lg-offset-3'>";
		$html_string .= "<h2>CREATE POLL</h2>";
		$html_string .= "<div class='clear'></div>";	
		
		$html_string .= "<p>";
			$html_string .= "<label class='form_label_header'> Question: </label>";
			$html_string .= "<input type='text' id='form_question' placeholder='Type your question here..' maxlength='100'>";
		$html_string .= "</p>";
		$html_string .= "<p class='form_p_option'>";
			$html_string .= "<label class='form_label_header'> Poll Options: </label>";
			$html_string .= "<input type='text' id='form_option_1' class='form_option' placeholder='1..' maxlength='75'>";
			$html_string .= "<input type='text' id='form_option_2' class='form_option' placeholder='2..' maxlength='75'>";
			$html_string .= "<input type='text' id='form_option_3' class='form_option' placeholder='3..' maxlength='75' onKeyUp='form_add_input()'>";
		 $html_string .= "</p>";
		 
		 $html_string .= "<p class='form_checkbox'>";
			$html_string .= "<input type='checkbox' id='form_checkbox_public' checked>";
			$html_string .= "<label for='form_checkbox_public' class='form_label_checkbox'> Public </label> <br>";
			
			$html_string .= "<input type='checkbox' id='form_checkbox_IP' checked>";
			$html_string .= "<label for='form_checkbox_IP' class='form_label_checkbox'> Only one vote per IP </label>";
		 $html_string .= "</p>";
		 
		$html_string .= "<button id='form_create_btn_create' class='form_btn'>Create</button>";
	$html_string .= "</form>".$ln;
	
	return $html_string;
}

function show_public_poll($page){
	global $PDO;
	
	if($page == "homepage") $list_limit = 10;
	else $list_limit = 20;
	
	$html_string = "";
	$html_string .= "<div id='div_list_".$page."' class='public_vote_section margin_top_section row col-md-12'>";
	
	if($page == "public"){
		// $html_string .= "<div class='row'>";
			$html_string .= "<form action='javascript:void(0);' class='div_main_border form_search col-xs-10 col-xs-offset-1 col-md-65-pourcent col-md-offset-0'>";
				$html_string .= "<input type='text' id='form_search_text' placeholder='Search..' maxlength='50'>";
				$html_string .= "<button id='form_search_btn' style='' class='form_btn'>Search</button>";
			$html_string .= "</form>";
			$html_string .= "<div class='clear'></div>";
		// $html_string .= "</div>";
	}
		// COLUMN MOST VOTED
		$html_string .= "<div class='div_column_bottom div_main_border col-xs-10 col-xs-offset-1 col-md-30-pourcent col-md-offset-0'><h3>MOST VOTED</h3> ";
		$html_string .= "<div class='clear'></div>";	
		
			
			$most_voted_poll = $PDO->prepare("SELECT v.id_poll, COUNT(v.id) FROM voter AS v LEFT JOIN poll AS p ON v.id_poll = p.id WHERE p.public = 1 GROUP BY v.id_poll ORDER BY count(v.id) DESC LIMIT ".$list_limit);
			$most_voted_poll->execute();
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
			
			$html_string .= "<ol>";
			foreach($where_poll AS $id_poll){
				if(isset($most_voted_formated[$id_poll])){
					$html_string .= show_poll_for_list($most_voted_formated[$id_poll]);  
				}
			}
			$html_string .= "<li style='list-style:none; text-decoration:none;'><a title='More' class='public_list_more public_list_most_voted'> More ... </a></li>";
			$html_string .= "</ol>";
			
		$html_string .= "</div>";
		
		
		// COLUMN TRENDING
		$html_string .= "<div class='div_column_bottom div_main_border col-xs-10 col-xs-offset-1 col-md-30-pourcent col-md-offset-5-pourcent'><h3>TRENDING</h3>";
		$html_string .= "<div class='clear'></div>";	
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
			foreach($poll_vote AS $id_poll => $info){
				$poll_trending[$id_poll]["question"] = $poll_info[$id_poll]["question"];
				$poll_trending[$id_poll]["url"] = $poll_info[$id_poll]["url"];
				$inc_trending++;
				if($inc_trending == $list_limit) break;
			}
			
			$html_string .= "<ol>";
			foreach($poll_trending AS $poll_info){
				$html_string .= show_poll_for_list($poll_info);  
			}
			$html_string .= "<li style='list-style:none; text-decoration:none;'><a title='More' class='public_list_more public_list_trending'> More ... </a></li>";
			$html_string .= "</ol>";
		$html_string .= "</div>";
		
		
		// COLUMN NEW
		$html_string .= "<div class='div_column_bottom div_main_border col-xs-10 col-xs-offset-1 col-md-30-pourcent col-md-offset-5-pourcent'><h3>NEW</h3>";
		$html_string .= "<div class='clear'></div>";	
		
			$poll_question = $PDO->prepare("SELECT question, url FROM poll WHERE public = 1 ORDER BY id DESC LIMIT ".$list_limit);
			$poll_question->execute();
			$poll_question_list = $poll_question->fetchAll();
			
			$html_string .= "<ol>";
			foreach($poll_question_list AS $poll_info){
				$html_string .= show_poll_for_list($poll_info);  
			}
			$html_string .= "<li style='list-style:none; text-decoration:none;'><a title='More' class='public_list_more public_list_new'> More ... </a></li>";
			$html_string .= "</ol>";	
		$html_string .= "</div>";
	
	$html_string .= "</div>";
	return $html_string;
}



function show_vote($vote){
	global $PDO;
	
	$info_poll = $PDO->prepare("SELECT p.id AS id_poll, p.question, p.one_vote_ip, po.id AS id_option, po.texte  FROM poll AS p LEFT JOIN poll_option AS po ON p.id = po.id_poll WHERE p.url = :url");
	$info_poll->execute(['url' => $vote]);
	$info_poll = $info_poll->fetchAll();
	
	$input_checkbox = 1;
	// if(count($info_poll) == 2) $input_checkbox = 0; // If there is only 2 options, I put radio button
	
	$inc_input = 1;
	$html_string_option_input = "";
	foreach($info_poll AS $info){
		if(!isset($id_poll)){
			$id_poll = protect_xss($info["id_poll"]);
			$question = protect_xss($info["question"]);
			$one_vote_ip = protect_xss($info["one_vote_ip"]);
		}
		
		$id_option = protect_xss($info["id_option"]);
		$texte = protect_xss($info["texte"]);
		
		
		$html_string_option_input .= "<div id='form_vote_div_".$id_option."' class='form_vote_div_order'></div>";
		if($input_checkbox == 1) $html_string_option_input .= "<input type='checkbox' id='form_vote_input_".$inc_input."' class='form_vote_checkbox'>";
		else $html_string_option_input .= "<input type='radio' id='form_vote_input_".$inc_input."' name='form_vote_radio' class='form_vote_radio'>";
		$html_string_option_input .= "<label class='form_label_checkbox' for='form_vote_input_".$inc_input++."'> ".$texte." </label><br>";
	}
				
	if(!isset($id_poll)){ // If poll not found
		return show_404_page("poll");
	}
	
	if($one_vote_ip == 1){
		$ip = $_SERVER['REMOTE_ADDR'];
		if(array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
			$ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = $ip[0];
		}
		
		$stmt = $PDO->prepare("SELECT id FROM voter WHERE address_ip = :ip AND id_poll = :id_poll");
		$stmt->execute(['ip' => $ip, 'id_poll' => $id_poll]);
		$info_voter = $stmt->fetch(PDO::FETCH_ASSOC);
		
		if(isset($info_voter["id"]) && $info_voter["id"] > 0){ // Check if voter already voted
			if($_SERVER['REMOTE_ADDR'] == "::1") $redirect_to = "/Better_vote";
			else $redirect_to = "";
			header("Location: ".$redirect_to."/r/".$vote);
		}
	}
	
	$html_string = "";
	$html_string .= "<div class='container'>";
		$html_string .= show_main_title("vote");
		
		$html_string .= "<form action='javascript:void(0);' id='form_vote_".$id_poll."' class='form_vote col-xs-10 col-xs-offset-1 col-md-8 col-md-offset-2 col-lg-6 col-lg-offset-3'>";
			$html_string .= "<h2>VOTE</h2>";
			$html_string .= "<div class='clear'></div>";	
			
			if($input_checkbox == 1){
				$html_string .= "<div class='detail_font_size_text' style='position: absolute; right: 30px; top: 28px;'>";
					$html_string .= "Rank <b>only</b> the choices you approve";
				$html_string .= "</div>";
			}
			
			$html_string .= "<p>";
				$html_string .= "<label class='form_label_header'> Question: ".$question." </label>";
			$html_string .= "</p>";

				$html_string .= $html_string_option_input;
				
				$html_string .= "<p style='height:7px;'></p>";
			// $html_string .= "<button id='form_vote_btn_share' class='form_btn form_btn_share'>Share</button>";
			
			
			$html_string .= "<button id='form_vote_btn_vote' style='float:left;' class='form_btn'>Vote</button>";
			$html_string .= "<a href='r/".$vote."' id='form_vote_btn_result' role='button' class='form_btn' style='margin-right:10px;'>Result</a>";
		$html_string .= "</form>";	
	$html_string .= "</div>";	

	return $html_string; 
}


function show_about_page(){
	$html_string = "";
	
	$html_string .= "<div class='container'>";
	
		$html_string .= show_main_title("about");
		
		$html_string .= "<div class='div_main_border col-xs-10 col-xs-offset-1 col-md-8 col-md-offset-2'>";	
			$html_string .= "<h2 style='margin: 10px 0; padding-bottom: 5px; display:block; float:left; border-bottom: 2px solid #424247; color: #424247;'>FREQUENTLY ASKS QUESTIONS</h2>";	
			$html_string .= "<div class='clear'></div>";
			$html_string .= "<h2 style='margin-top: 15px; color: #424247;'>Why did you make this site?</h2>";	
			$html_string .= "<p class='font_size_text'>I am from Canada and during his 2015 election campaign, Justin Trudeau talked about replacing the actual voting system (First-Past-The-Post) to the Alternative Voting. That made me interested in learning about the different voting systems. After doing some research, I realized there is no perfect one. I tought, maybe we can use multiple voting systems and see who won the most. That could be the 'real' winner. That's why I made Better-Vote. I also think this website is a great tool to visualize that there can be different winner depending of the voting system. </p>";	
			
			$html_string .= "<h2 style='margin-top: 35px; color: #424247;'>Can I tell you way(s) to improve the vote?</h2>";	
			$html_string .= "<p class='font_size_text'>Yes! There is clearly features that could be added or ways to show the results in a better way. If you want to help me improve it, you can do it by mail : bettervote1@gmail.com</p>";	
			
			$html_string .= "<h2 style='margin-top: 35px; color: #424247;'>Why is there a maximum of 10 candidates?</h2>";	
			$html_string .= "<p class='font_size_text'>The maximum is 10 for the moment just because some charts looks weird with more than 10. I will do an update if the need is there.</p>";	
			
			$html_string .= "<h2 style='margin-top: 35px; color: #424247;'>Can I donate?</h2>";	
			$html_string .= "<p class='font_size_text'>What a funny question! Yes, you can! This service is free. If you like it and want to donate, you can do it here : </p>";	
			
			$html_string .= "<form action='https://www.paypal.com/cgi-bin/webscr' method='post' target='_top'>";
				$html_string .= "<input type='hidden' name='cmd' value='_s-xclick'>";
				$html_string .= "<input type='hidden' name='encrypted' value='-----BEGIN PKCS7-----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-----END PKCS7-----'>";
				$html_string .= "<input type='image' src='https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif' border='0' name='submit' alt='PayPal - The safer, easier way to pay online!'>";
				$html_string .= "<img alt='' border='0' src='https://www.paypalobjects.com/fr_CA/i/scr/pixel.gif' width='1' height='1'>";
			$html_string .= "</form>";

		$html_string .= "</div>";	
	$html_string .= "</div>";	
	
	
	return $html_string;
}
?>	

<script src="includes/general.min.js"></script> 	

</body>
</html>
