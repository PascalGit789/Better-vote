<?php

function show_result($url_poll, $return_array_result){
	global $PDO;
	
	// Initialise the results arrays with the poll option
	if(true){
		$info_poll = $PDO->prepare("SELECT p.id AS id_poll, p.one_vote_ip, p.question, po.id AS id_option, po.texte  FROM poll AS p LEFT JOIN poll_option AS po ON p.id = po.id_poll WHERE p.url = :url");
		$info_poll->execute(['url' => $url_poll]);
		
		$all_vote_result = [];

		$FPTP_result = []; // Contain [id_option] => score
		$approval_result = [];
		$borda_result = [];
		
		$condorcet_winner = []; 
		$condorcet_opponent = []; 
		
		$array_option_texte = []; // Contain [id_option] => texte 
		$where_option = [];
		$where_option_in = "";
		foreach($info_poll AS $info){
			if(!isset($id_poll)){
				$id_poll = protect_xss($info["id_poll"]);
				$question = protect_xss($info["question"]);
				$one_vote_ip = protect_xss($info["one_vote_ip"]);
			}
			$id_option = protect_xss($info["id_option"]);
			$texte = protect_xss($info["texte"]);
			
			$where_option[] = $id_option;
			$where_option_in .= "?, ";
			
			if(!isset($FPTP_result[$id_option])){
				$all_vote_result[$id_option] = [];
				
				$FPTP_result[$id_option] = 0;
				$approval_result[$id_option] = 0;
				$borda_result[$id_option] = 0;
				
				$condorcet_winner[$id_option] = 0;
				$condorcet_opponent[$id_option] = 0;
			}
			
			$array_option_texte[$id_option] = $texte;
		}
		$where_option_in = rtrim($where_option_in, ', ');
	}
	
	// Pass every vote. 
	// - Create the most of the result (All vote, FPTP, AV first vote(copied of FPTP after the foreach), Borda)
	// - Create an array of $answer_order   Used for the condorcet method
	if(true){
		if(!isset($id_poll)) return show_404_page("poll");
		$result_poll = $PDO->prepare("SELECT id_poll_option, vote_order FROM poll_vote WHERE id_poll_option IN (".$where_option_in.")");
		$result_poll->execute($where_option);
		$result_poll_fetch = $result_poll->fetchAll();
		
		$number_candidates = count($array_option_texte);
		$answer_order = [];
		$temp_answer = [];
		$vote_order_inc = 0;
		foreach($result_poll_fetch AS $info){
			$id_poll_option = protect_xss($info["id_poll_option"]);
			$vote_order = protect_xss($info["vote_order"]);
			
			// Order all the vote for the  All vote chart
			if(!isset($all_vote_result[$id_poll_option][$vote_order])) $all_vote_result[$id_poll_option][$vote_order] = 0;
			$all_vote_result[$id_poll_option][$vote_order] += 1;
			
			// Get the result for the FPTP. And this array is cloned for the first iteration of the AV result
			if($vote_order == 1){
				$FPTP_result[$id_poll_option] += 1;
			}
			
			// For AV. Create an array with the answer in order
			$vote_order_inc++;
			if($vote_order_inc != $vote_order){
				$answer_order[] = $temp_answer;
				$vote_order_inc = 1;
				$temp_answer = [];
			}
			$temp_answer[] = $id_poll_option;
			
			if(!isset($approval_result[$id_poll_option])) $approval_result[$id_poll_option] = 0;
			$approval_result[$id_poll_option] += 1;
			
			// Borda
			if(!isset($borda_result[$id_poll_option])) $borda_result[$id_poll_option] = 0;
			$borda_result[$id_poll_option] += $number_candidates - ($vote_order - 1);
			
		}
		$answer_order[] = $temp_answer;
		
		// Order by vote order
		foreach($all_vote_result AS &$all_result_by_option){
			ksort($all_result_by_option);
		}
	}
	
	// Alternative vote   - find the winner
	if(true){
		// $AV_result = []; // Contain [id_option] => score  . At first it only contain the first vote. And then it goes in the while() to get the winner
		$AV_result = $FPTP_result;
		
		$winner_found = 0;
		$nbr_total_vote = get_total_vote($AV_result);
		$loser_comp = [];
		while(!$winner_found){     
			if(is_winner_AV($AV_result, $nbr_total_vote)){
				$winner_found = 1;
				break;
			}
			
			if(!drop_loser_AV($AV_result, $answer_order, $loser_comp)){
				$winner_found = 1;
			}
			
		}
		
		
		
		// Fill result with missing option
		foreach($array_option_texte AS $id_option => $info){
			if(!isset($AV_result[$id_option])) $AV_result[$id_option] = 0;
		}
		ksort($AV_result);
	}
	
	// Supplementary vote
	if(true){
		$supplementary_result = $FPTP_result;
		
		$winner_found = 0;
		$nbr_total_vote = get_total_vote($supplementary_result);
		
		if(is_majority_winner($supplementary_result, $nbr_total_vote)){
			$winner_found = 1;
		}else{ // No winner found -> Drop every candidates but last 2 and distribute their vote
			drop_loser_supplementary($supplementary_result, $answer_order);
		}
		
		// Fill result with missing option
		foreach($array_option_texte AS $id_option => $info){
			if(!isset($supplementary_result[$id_option])) $supplementary_result[$id_option] = 0;
		}
		ksort($supplementary_result);
	}
	
	// Condorcet method  - find the winner
	if(true){
		$condorcet_opposition = [];
		foreach($answer_order AS $order_option){ 
			if(!isset($order_option[0])) continue; // If no vote
			
			// Count the number of time an option is placed before another 			
			$winner_i = 0;
			$loser_i = 1;
			
			while(isset($order_option[$winner_i])){
				while(isset($order_option[$loser_i])){
					if(!isset($condorcet_opposition[$order_option[$winner_i]][$order_option[$loser_i]])) $condorcet_opposition[$order_option[$winner_i]][$order_option[$loser_i]] = 1;
					else $condorcet_opposition[$order_option[$winner_i]][$order_option[$loser_i]] += 1;
					
					$loser_i++;
				}
				$winner_i++;
				$loser_i = $winner_i + 1;
			}
			
			// To count the option that are not approved
			foreach($condorcet_opponent AS $id_option_loser => $zero_opp){ 
				//Check if $id_option_loser is in one of the choices. If not, that means $id_option_loser lost against all the approved choices
				$loser = 1; // Lost by default
				foreach($order_option AS $id_option_vote){
					if($id_option_loser == $id_option_vote) $loser = 0; // If this id_option_loser == one of the votes. That means $id_option_loser is not a loser! 
				}
				if($loser == 1){
					$i_inc = 0;
					while(isset($order_option[$i_inc])){ // If a candidate is not approved, he lost against all the approved.
						if(!isset($condorcet_opposition[$order_option[$i_inc]][$id_option_loser])) $condorcet_opposition[$order_option[$i_inc]][$id_option_loser] = 1;
						else $condorcet_opposition[$order_option[$i_inc]][$id_option_loser] += 1;
						
						$i_inc++;
					}
					
					
				}
			}
		}
		
		// Remove the condorcets losers
		foreach($condorcet_winner AS $id_option_win => $zero_win){ 
			foreach($condorcet_opponent AS $id_option_opp => $zero_opp){ 
				if($id_option_win == $id_option_opp || !isset($condorcet_opposition[$id_option_opp][$id_option_win])) continue;
				
				
				// IF $id_option_win lose it's battle against one of it's opponent
				if(!isset($condorcet_opposition[$id_option_win][$id_option_opp]) && isset($condorcet_opposition[$id_option_opp][$id_option_win]) || $condorcet_opposition[$id_option_win][$id_option_opp] < $condorcet_opposition[$id_option_opp][$id_option_win]){ 
					unset($condorcet_winner[$id_option_win]);
				}
			}
		}
	}
	

	// Find the winner of each voting system
	if(true){
		$FPTP_winner = array_keys($FPTP_result, max($FPTP_result));
		if(is_array($FPTP_winner) && count($FPTP_winner) >= 2){
			$FPTP_winner = 0;
			$FPTP_winner_position = null;
		}
		else{
			$FPTP_winner = $FPTP_winner[0];
			$FPTP_winner_position = array_search($FPTP_winner, array_keys($FPTP_result));
		}
		
		
		$AV_winner = array_keys($AV_result, max($AV_result));
		if(is_array($AV_winner) && count($AV_winner) >= 2){
			$AV_winner = 0;
			$AV_winner_position = null;
		}
		else{
			$AV_winner = $AV_winner[0];
			$AV_winner_position = array_search($AV_winner, array_keys($AV_result));
		}
		
		$approval_winner = array_keys($approval_result, max($approval_result));
		if(is_array($approval_winner) && count($approval_winner) >= 2){
			$approval_winner = 0;
			$approval_winner_position = null;
		}
		else{
			$approval_winner = $approval_winner[0];
			$approval_winner_position = array_search($approval_winner, array_keys($approval_result));
		}
		
		
		$borda_winner = array_keys($borda_result, max($borda_result));
		if(is_array($borda_winner) && count($borda_winner) >= 2){
			$borda_winner = 0;
			$borda_winner_position = null;
		}
		else{
			$borda_winner = $borda_winner[0];
			$borda_winner_position = array_search($borda_winner, array_keys($borda_result));
		}
		
		$supplementary_winner = array_keys($supplementary_result, max($supplementary_result));
		if(is_array($supplementary_winner) && count($supplementary_winner) >= 2){
			$supplementary_winner = 0;
			$supplementary_winner_position = null;
		}
		else{
			$supplementary_winner = $supplementary_winner[0];
			$supplementary_winner_position = array_search($supplementary_winner, array_keys($supplementary_result));
		}
		
		
		if(count($condorcet_winner) == 1){
			reset($condorcet_winner);
			$condorcet_winner = key($condorcet_winner);
		}
		else $condorcet_winner = 0;
	}
	
	
	// Option color               Same arrays in JS  -    Need to change in both places
	$array_backgroundColor = ["#5093ce", "#43404c", "#fab657", "#871887", "#56fefe", "#8d8d19", "#808080", "#eaaede","#800000", "#008000"];
	

	// Create HTML for condorcet method 
	if(true){
		$html_condorcet = "";
		$winner = 0;
		if($condorcet_winner > 0){
			$winner = $condorcet_winner;
		}
		
			
		$array_letter_option_id = [];	
		$letter_inc = "A";	
		$color_inc = 0;
		$color_key_winner = 0;
		$option_id_color = [];		// Used in the winner section
		
		$html_condorcet .= "<table class='table_border'>";
		$html_condorcet .= "<tr style='font-size:1.05rem;'>";
		$html_condorcet .= "<th style='border: 3px solid #898989; border-bottom: 0;'>Candidate</th><th style='border: 3px solid #898989; border-bottom: 0;'>Corresponding letter</th>";
		$html_condorcet .= "</tr>";
		foreach($condorcet_opponent AS $id_option => $zero_opp){ 
			$class_winner = '';
			if($winner == $id_option){
				$class_winner = 'table_option_winner';
				$color_key_winner = $color_inc;
			}
				
			$html_condorcet .= "<tr class='".$class_winner."'><td style='border:3px solid ".$array_backgroundColor[$color_inc]."'>".$array_option_texte[$id_option]."</td><td style='border:3px solid ".$array_backgroundColor[$color_inc]."'> ".$letter_inc."</td></tr>";
			
			$array_letter_option_id[$id_option] = $letter_inc;
			$option_id_color[$id_option] = $array_backgroundColor[$color_inc];
			$letter_inc++;
			$color_inc++;
		}
		$html_condorcet .= "</table>";
		
		// https://www.w3.org/WAI/tutorials/tables/two-headers/          accessibilty for table with 2 header
		$html_condorcet .= "<br>";
		$html_condorcet .= "<table class='table_condorcet table_border'>";
			$html_condorcet .= "<tr>";	
			$html_condorcet .= "<th> Prefer ... Over </th>";	
			foreach($condorcet_opponent AS $id_option => $zero_opp){ 
				$winner_style = '';
				if($winner == $id_option) $winner_style = 'font-weight:bold;';
				$html_condorcet .= "<th style='".$winner_style."'>".$array_letter_option_id[$id_option]."</th>";						
			}
			$html_condorcet .= "</tr>";	
			
			foreach($condorcet_opponent AS $id_option => $zero_opp){ 
				$style_winner = "";
				if($winner == $id_option){
					$style_winner = "style='font-weight:bold; border:2px solid ".$array_backgroundColor[$color_key_winner].";'";
				}
				$html_condorcet .= "<tr ".$style_winner.">";	
				$html_condorcet .= "<th>".$array_letter_option_id[$id_option]."</th>";	
				foreach($condorcet_opponent AS $id_option_horizontal => $zero_opp_horizontal){ 
					if($id_option == $id_option_horizontal) $html_condorcet .= "<td>—</td>";
					else $html_condorcet .= "<td>".(isset($condorcet_opposition[$id_option][$id_option_horizontal])?$condorcet_opposition[$id_option][$id_option_horizontal]:"0")."</td>";	
				}
				$html_condorcet .= "</tr>";	
			}
			
		
		$html_condorcet .= "</table>";	
	}
	
	
	// Compile the winner of each voting system
	if(true){
		$compilation_winner = [];
		$nbr_vote_counted = 0;
		$array_winner = ["First Past The Post" => $FPTP_winner, "Alternative Voting" => $AV_winner, "Supplementary Vote" => $supplementary_winner, "Borda Count" => $borda_winner, "Approval Voting" => $approval_winner, "Condorcet Method" => $condorcet_winner];
		foreach($array_winner AS $voting_system => $winner){
			if(!isset($compilation_winner[$winner])){
				$compilation_winner[$winner] = [];
				$compilation_winner[$winner]["nbr_won"] = 0;
				$compilation_winner[$winner]["system_won"] = [];
				
			}
			$compilation_winner[$winner]["nbr_won"] += 1;
			$compilation_winner[$winner]["system_won"][] = $voting_system;
			if($winner != 0) $nbr_vote_counted += 1;
		}
		
		$all_around_winner = 0;
		$all_around_winner_score = 0;
		foreach($compilation_winner AS $id_option => $data){
			if($data["nbr_won"] > $all_around_winner_score){
				$all_around_winner = $id_option;
				$all_around_winner_score = $data["nbr_won"];
			}
		}
		
		// To know if there is more than 1 all around winner
		foreach($compilation_winner AS $id_option => $data){
			if($data["nbr_won"] == $all_around_winner_score && $id_option != $all_around_winner) $all_around_winner = 0;
		}
		
		// Order winner by best to worst
		function order_winner($a, $b) {
			return $b["nbr_won"] - $a["nbr_won"];
		}
		uasort($compilation_winner, "order_winner");
	}
	
	// Create HTML for the winner section
	if(true){
		$btn_vote = 1;
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
				$btn_vote = 0;
			}
			
		}
		
		// If at least one candidate won 1 voting system.     $compilation_winner[0] is set when no one won a voting system.
		$number_votes = array_sum($FPTP_result);
		$html_winner_section = "";
		$html_winner_section .= "<table class='table_border'>";
		$html_winner_section .= "<tr style='font-size:1.05rem;'>";
		$html_winner_section .= "<th style='border: 3px solid #898989; border-bottom: 0;'>Candidate</th><th style='border: 3px solid #898989; border-bottom: 0;'>Voting System Won</th>";
		$html_winner_section .= "</tr>";
		
		foreach($compilation_winner AS $id_option => $data){
			if($id_option == 0) continue;
			
			$class_winner = "";
			if($id_option == $all_around_winner)	$class_winner = 'table_option_winner'; 
			$html_winner_section .= "<tr class='".$class_winner."'>";
			
			$html_winner_system = " (";
			foreach($data["system_won"] AS $i_inc => $voting_system){
				switch($voting_system){
						case "First Past The Post":
							$anchor = 'result_FPTP';
							break;
						case "Alternative Voting":
							$anchor = 'result_AV';
							break;	
						case "Approval Voting":
							$anchor = 'result_approval';
							break;		
						case "Borda Count":
							$anchor = 'result_borda';
							break;	
						case "Supplementary Vote":
							$anchor = 'result_supplementary';
							break;
						case "Condorcet Method":
							$anchor = 'result_condorcet';
							break;				
						default:
							$anchor = 'result_FPTP';
				}
				$html_winner_system .= "<span style='cursor:pointer; text-decoration: underline;' onClick='scrollToAnchor(\"".$anchor."\")'>".$voting_system."</span>,<br> ";
			}
			$html_winner_system = substr($html_winner_system, 0, -6).")"; // Remove last ',<br> '

			$html_winner_section .= "<td style='border:3px solid ".$option_id_color[$id_option]."'>".$array_option_texte[$id_option]."</td><td style='border:3px solid ".$option_id_color[$id_option]."'>".$data["nbr_won"].$html_winner_system."</td>";

			$html_winner_section .= "</tr>";
		}
		
		if(isset($compilation_winner[0])){ // Tie system
			$html_tie_system = " (";
			foreach($compilation_winner[0]["system_won"] AS $i_inc => $voting_system){
				switch($voting_system){
						case "First Past The Post":
							$anchor = 'result_FPTP';
							break;
						case "Alternative Voting":
							$anchor = 'result_AV';
							break;	
						case "Approval Voting":
							$anchor = 'result_approval';
							break;							
						case "Borda Count":
							$anchor = 'result_borda';
							break;		
						case "Supplementary Vote":
							$anchor = 'result_supplementary';
							break;
						case "Condorcet Method":
							$anchor = 'result_condorcet';
							break;				
						default:
							$anchor = 'result_FPTP';
				}
				$html_tie_system .= "<span style='cursor:pointer; text-decoration: underline;' onClick='scrollToAnchor(\"".$anchor."\")'>".$voting_system."</span>,<br> ";
			}
			$html_tie_system = substr($html_tie_system, 0, -6).")"; // Remove last ',<br> '
			
			$nbr_tie = count($compilation_winner[0]["system_won"]);
			$html_winner_section .= "<tr>";
			$class_border = '';
			if($all_around_winner != 0) $class_border = 'border-top: 0;';
			$html_winner_section .= "<td style='border: 3px solid #898989; ".$class_border."'>No Winner - Tie</td><td style='border: 3px solid #898989; ".$class_border."'>".$nbr_tie." ".$html_tie_system."</td>";
			$html_winner_section .= "</tr>";
		}
		
		$html_winner_section .= "<tr>";
		if(isset($_SESSION["just_voted"]) && $_SESSION["just_voted"] == 1){
			$html_just_voted = "<div class='pop_up_just_voted confirmation_pop_up'> + 1  <span style='float: right;'>Your vote counted!</span></div>";
			unset($_SESSION["just_voted"]);
		}else $html_just_voted = "";
		
		$html_winner_section .= "<td style='border: 3px solid #898989; '>Number of votes</td><td style='border: 3px solid #898989;'> ".$number_votes." ".$html_just_voted." </td>";
		$html_winner_section .= "</tr>";
		
		$html_winner_section .= "</table>";
		
		if($url_poll == "84740512"){
			$html_winner_section .= "<div style='margin-top:10px;'>";
				$html_winner_section .= "* This poll has false votes to show that there are different winners depending of the system. The idea of this poll come from this <a style='color:black; text-decoration: underline;' target='_blank' rel='noopener' href='https://plus.maths.org/content/which-voting-system-best'>site</a>. ";
			$html_winner_section .= "</div>";
		}

		
		$html_winner_section .= "<div style='height:65px;'>";
			if($btn_vote == 1) $html_winner_section .= "<a href='v/".$url_poll."' role='button' class='form_btn' style='position:absolute; bottom:20px; left:30px;' tabindex='0'>Vote</a>";
			
			// $html_winner_section .= "<a role='button' class='form_btn form_btn_share' style='position:absolute; bottom:20px; right:30px;'>Share</a>";
			$html_winner_section .= "<a role='button' class='form_btn' style='position:absolute; bottom:20px; right:30px;' onClick='refresh_chart()' tabindex='0'>Refresh</a>";
			$html_winner_section .= "<div class='pop_up_refresh confirmation_pop_up'> Done!</div>";
			
			$html_winner_section .= "<div class='clear'></div>";
		$html_winner_section .= "</div>";
	}
	

	// Called to refresh the page without the re-creating all the HTML
	if($return_array_result == 1){
		$array_winner_score = ["FPTP" => $FPTP_winner_position, "AV" => $AV_winner_position, "approval" => $approval_winner_position, "borda" => $borda_winner_position, "supplementary" => $supplementary_winner_position]; // I don't need the condorcet winner. The condorcet winner is updated in the html
		
		$array_result = [];
		$array_result["all_vote"] = $all_vote_result;
		$array_result["all_around_winnner"] = $html_winner_section;
		$array_result["FPTP"] = $FPTP_result;
		$array_result["AV"] = $AV_result;
		$array_result["approval"] = $approval_result;
		$array_result["borda"] = $borda_result;
		$array_result["condorcet"] = $html_condorcet;
		$array_result["supplementary"] = $supplementary_result;
		$array_result["winner_score"] = $array_winner_score;
		
		
		return $array_result;
	}
	
	
	// Hidden Results use with the JS to create the graph
	if(true){
		$html_string = "";
		$html_string .= "<div class='display_none all_vote_result'>";
		$inc_option = 1;
		foreach($all_vote_result AS $id_option => $result){
			$html_string .= "<div id='all_vote_".$id_option."' class='all_vote_each'>";
				$html_string .= "<div class='all_vote_texte'>".$array_option_texte[$id_option]."</div>";
				$html_string .= "<div class='all_vote_result_1'>".(isset($result["1"])?$result["1"]:0)."</div>";
				$html_string .= "<div class='all_vote_result_2'>".(isset($result["2"])?$result["2"]:0)."</div>";
				$html_string .= "<div class='all_vote_result_3'>".(isset($result["3"])?$result["3"]:0)."</div>";
			$html_string .= "</div>";
		}
		$html_string .= "</div>";
		
		$html_string .= "<div class='display_none FPTP_result'>";
		$inc_option = 1;
		foreach($FPTP_result AS $id_option => $result){
			$html_string .= "<div id='FPTP_texte_".$id_option."' class='FPTP_result_option'>".$array_option_texte[$id_option]."</div> <div class='FPTP_result_".$inc_option++."'>".$result."</div>";
		}
		$html_string .= "</div>";
		
		
		$html_string .= "<div class='display_none AV_result'>";
		$inc_option = 1;
		foreach($AV_result AS $id_option => $result){
			$html_string .= "<div id='AV_texte_".$id_option."' class='AV_result_option'>".$array_option_texte[$id_option]."</div> <div class='AV_result_".$inc_option++."'>".$result."</div>";
		}
		$html_string .= "</div>";
		
		$html_string .= "<div class='display_none approval_result'>";
		$inc_option = 1;
		foreach($approval_result AS $id_option => $result){
			$html_string .= "<div id='approval_texte_".$id_option."' class='approval_result_option'>".$array_option_texte[$id_option]."</div> <div class='approval_result_".$inc_option++."'>".$result."</div>";
		}
		$html_string .= "</div>";
		
		
		$html_string .= "<div class='display_none borda_result'>";
		$inc_option = 1;
		foreach($borda_result AS $id_option => $result){
			$html_string .= "<div id='borda_texte_".$id_option."' class='borda_result_option'>".$array_option_texte[$id_option]."</div> <div class='borda_result_".$inc_option++."'>".$result."</div>";
		}
		$html_string .= "</div>";
		
		
		$html_string .= "<div class='display_none supplementary_result'>";
		$inc_option = 1;
		foreach($supplementary_result AS $id_option => $result){
			$html_string .= "<div id='supplementary_texte_".$id_option."' class='supplementary_result_option'>".$array_option_texte[$id_option]."</div> <div class='supplementary_result_".$inc_option++."'>".$result."</div>";
		}
		$html_string .= "</div>";
	}
	
	// HTML Front-end
	if(true){
		$html_string .= "<div class='container page_result'>";
			$html_string .= "<h1 class='main_title'><a href='./'> ".ucfirst($question)."</a></h1>";
			
			$html_string .= "<div class='row'>";
				// All Vote section
				if(true){
					$html_string .= "<div class='div_main_border col-md-7'>";
						$html_string .= "<div class='result_header_div'>";
							$html_string .= "<h2 class='margin_title_result'>First Three Choices</h2>";
							$html_string .= "<div class='result_click_detail detail_font_size_text'> Click for detail <div class='result_click_down_arrow'> &#9661; </div></div>";
							$html_string .= "<div class='result_header_detail'> This is a chart of the <b>first three choices</b>. You might be able to tell the winner just by looking at this chart but most of the times you need to choose a voting system.</div>";
						$html_string .= "</div>";
						$html_string .= "<div class='chart' style='width:100%; min-height:275px; max-height:600px;'>";
							$html_string .= "<canvas id='chart_all_vote' width='600' height='400' style='max-height:400px;'></canvas>";
						$html_string .= "</div>";
					$html_string .= "</div>";
				}
				// Winner section
				if(true){
					$html_string .= "<div class='div_main_border div_result_winner col-md-4 col-md-offset-1' style='min-height:525px;'>";
						$html_string .= "<div class='result_header_div'>";
							$html_string .= "<h2 class='margin_title_result'>Winner?</h2>";
							$html_string .= "<div class='result_click_detail detail_font_size_text'> Click for detail <div class='result_click_down_arrow'> &#9661; </div></div>";
							$html_string .= "<div class='result_header_detail'> The <b>winner</b> is usually chosen by <b>one</b> voting system. There can be different winner depending of the voting system. We show you here who won the most voting system.</div>";
						$html_string .= "</div>";
						
						$html_string .= "<div id='div_all_around_winner_result'>";
							$html_string .= $html_winner_section;
						$html_string .= "</div>";
						
					$html_string .= "</div>";
				}
				
			$html_string .= "</div>";
			
			$html_string .= "<div class='row margin_top_section'>";
				// FPTP section
				if(true) {
					$html_string .= "<div id='result_FPTP' class='div_main_border col-md-5'>";
						$html_string .= "<div class='result_header_div'>";
							$html_string .= "<h2 class='margin_title_result'>First Past The Post</h2>";
							$html_string .= "<div class='result_click_detail detail_font_size_text'> Click for detail <div class='result_click_down_arrow'> &#9661; </div></div>";
							$html_string .= "<div class='result_header_detail'> The First Past The Post only count the <b> first vote.</b>";
						
							$html_string .= "<div class='header_more_info'>";
								$html_string .= "More Info: ";
								$html_string .= "<a target='_blank' rel='noopener' href='https://en.wikipedia.org/wiki/First-past-the-post_voting'>Wikipedia</a>";
							$html_string .= "</div>";
							
							$html_string .= "</div>";
						$html_string .= "</div>";
						$html_string .= "<div class='chart' style='width:70%; margin-left:15%; min-height:275px;'>";
								$html_string .= "<canvas id='chart_FPTP' class='pie'></canvas>";
						$html_string .= "</div>";
						$html_string .= "<div id='FPTP_pie'></div>";
					$html_string .= "</div>";
				}

				
				// AV section
				if(true){
					$html_string .= "<div id='result_AV' class='div_main_border col-md-5 col-md-offset-2'>";
						$html_string .= "<div class='result_header_div'>";
							$html_string .= "<h2 class='margin_title_result'>Alternative Voting</h2>";
							$html_string .= "<div class='result_click_detail detail_font_size_text'> Click for detail <div class='result_click_down_arrow'> &#9661; </div></div>";
							$html_string .= "<div class='result_header_detail'>The Alternative vote goes like this : ";
								$html_string .= "<ol style='margin-left:27px;'>";
									$html_string .= "<li>Count the <b>first vote</b>.</li>";
									$html_string .= "<li>If one candidate have more than 50% of vote, he is the <b>winner</b>.</li>";
									$html_string .= "<li>If there is only one candidate left, he is the <b>winner</b>.</li>";
									$html_string .= "<li>If <b>no winner</b> found, <b>remove</b> the one with the worst score and distribute their second vote.</li>";
									$html_string .= "<li>Go back to number 2 (until a <b>winner</b> is found).</li>";
								$html_string .= "</ol>";
								
								$html_string .= "<div class='header_more_info'>";
									$html_string .= "More Info: ";
									$html_string .= "<a target='_blank' rel='noopener' href='https://en.wikipedia.org/wiki/Instant-runoff_voting'>Wikipedia</a>";
								$html_string .= "</div>";
								
							$html_string .= "</div>";
						$html_string .= "</div>";
						
						$html_string .= "<div class='chart' style='width:70%; margin-left:15%; min-height:275px;'>";
								$html_string .= "<canvas id='chart_AV' class='pie'></canvas>";
						$html_string .= "</div>";
						
						$html_string .= "<div id='AV_pie'></div>";
					$html_string .= "</div>";
				}
			$html_string .= "</div>";
			
			
			$html_string .= "<div class='row margin_top_section'>";
				// Supplementary vote section
				if(true){
					$html_string .= "<div id='result_supplementary' class='div_main_border col-md-5'>";
						$html_string .= "<div class='result_header_div'>";
							$html_string .= "<h2 class='margin_title_result'>Supplementary vote</h2>";
							$html_string .= "<div class='result_click_detail detail_font_size_text'> Click for detail <div class='result_click_down_arrow'> &#9661; </div></div>";

							$html_string .= "<div class='result_header_detail'>The Supplementary vote goes like this : ";
								$html_string .= "<ol style='margin-left:27px;'>";
									$html_string .= "<li>Count the <b>first vote</b>.</li>";
									$html_string .= "<li>If one candidate have more than 50% of vote, he is the <b>winner</b>.</li>";
									$html_string .= "<li>If <b>no winner</b>, <b>remove</b> all the candidates but the best two.</li>";
									$html_string .= "<li>Distribute the <b>second vote</b> of thoses that voted for an eliminated candidate.</li>";
								$html_string .= "</ol>";
							
								$html_string .= "<div class='header_more_info'>";
									$html_string .= "More Info: ";
									$html_string .= "<a target='_blank' rel='noopener' href='https://en.wikipedia.org/wiki/Contingent_vote'>Wikipedia</a>";
								$html_string .= "</div>";
							
							$html_string .= "</div>";
						$html_string .= "</div>";
						
						$html_string .= "<div class='chart' style='width:70%; margin-left:15%; min-height:275px;'>";
								$html_string .= "<canvas id='chart_supplementary' class='pie'></canvas>";
						$html_string .= "</div>";
						
						$html_string .= "<div id='supplementary_pie'></div>";
					$html_string .= "</div>";
				}
				
				// Borda count section
				if(true){
					$html_string .= "<div id='result_borda' class='div_main_border col-md-5 col-md-offset-2'>";
						$html_string .= "<div class='result_header_div'>";
							$html_string .= "<h2 class='margin_title_result'>Borda Count</h2>";
							$html_string .= "<div class='result_click_detail detail_font_size_text'> Click for detail <div class='result_click_down_arrow'> &#9661; </div></div>";
							$html_string .= "<div class='result_header_detail'> The Borda count goes like this, where <b>n</b> is the number of candidates :";
								$html_string .= "<ul style='margin-left:27px; list-style:disc;'>";
									$html_string .= "<li>Every <b>first</b> vote give <b>n</b> points</li>";
									$html_string .= "<li>Every <b>second</b> vote give <b>n-1</b> points</li>";
									$html_string .= "<li>Every <b>third</b> vote give <b>n-2</b> points</li>";
									$html_string .= "<li>Every <b>...</b> vote give <b>...</b> points</li>";
								$html_string .= "</ul>";
								
								$html_string .= "<div class='header_more_info'>";
									$html_string .= "More Info: ";
									$html_string .= "<a target='_blank' rel='noopener' href='https://en.wikipedia.org/wiki/Borda_count'>Wikipedia</a>";
								$html_string .= "</div>";
								
							$html_string .= "</div>";
						$html_string .= "</div>";
						
						$html_string .= "<div class='chart' style='width:70%; margin-left:15%; min-height:275px;'>";
								$html_string .= "<canvas id='chart_borda' class='pie'></canvas>";
						$html_string .= "</div>";
						
						$html_string .= "<div id='borda_pie'></div>";
					$html_string .= "</div>";
				}
				
			$html_string .= "</div>";
			
			$html_string .= "<div class='row margin_top_section'>";
				// Approval Voting section
				if(true){
					$html_string .= "<div id='result_approval' class='div_main_border col-md-5'>";
						$html_string .= "<div class='result_header_div'>";
							$html_string .= "<h2 class='margin_title_result'>Approval Voting</h2>";
							$html_string .= "<div class='result_click_detail detail_font_size_text'> Click for detail <div class='result_click_down_arrow'> &#9661; </div></div>";
							$html_string .= "<div class='result_header_detail'> The approval voting count every <b>approved</b> vote for <b>one point</b>. The order of the choices don't matter for this system.";
								$html_string .= "<div class='header_more_info'>";
									$html_string .= "More Info: ";
									$html_string .= "<a target='_blank' rel='noopener' href='https://en.wikipedia.org/wiki/Approval_voting'>Wikipedia</a>";
								$html_string .= "</div>";
								
							$html_string .= "</div>";
						$html_string .= "</div>";
						
						$html_string .= "<div class='chart' style='width:100%; min-height:200px;'>";
								$html_string .= "<canvas id='chart_approval' class='pie'></canvas>";
						$html_string .= "</div>";
						
						$html_string .= "<div id='approval_pie'></div>";
					$html_string .= "</div>";
				}
			
				// Condorcet section
				if(true){
					$html_string .= "<div id='result_condorcet' class='div_main_border div_main_border_condorcet col-md-5 col-md-offset-2'>";
						$html_string .= "<div class='result_header_div'>";
							$html_string .= "<h2 class='margin_title_result'>Condorcet Method</h2>";
							$html_string .= "<div class='result_click_detail detail_font_size_text'> Click for detail <div class='result_click_down_arrow'> &#9661; </div></div>";
							$html_string .= "<div class='result_header_detail'> The Condorcet method finds the <b>winner</b> by doing a one-on-one with every participant. The winner needs to be prefered to every other opponent. I recommend to visit the Wikipedia link if you haven't heard of the Condorcet method before.";
								
								$html_string .= "<div class='header_more_info'>";
									$html_string .= "More Info: ";
									$html_string .= "<a target='_blank' rel='noopener' href='https://en.wikipedia.org/wiki/Condorcet_method'>Wikipedia</a>";
								$html_string .= "</div>";
							$html_string .= "</div>";
						$html_string .= "</div>";
						
						$html_string .= "<div id='div_condorcet_result'>";
							$html_string .= $html_condorcet;
						$html_string .= "</div>";
					$html_string .= "</div>";
				}
				
			$html_string .= "</div>";
		$html_string .= "</div>";	
	}

	return $html_string; 
}

function get_total_vote($array){
	$nbr_total_vote = 0;
	foreach($array AS $id_option => $nbr_vote){
		$nbr_total_vote += $nbr_vote;
	}
	return $nbr_total_vote;
}

function is_winner_AV($AV_result, $nbr_total_vote){
	// Check if an option have > than 50% of vote  --> winner!
	if(is_majority_winner($AV_result, $nbr_total_vote) == 1){
		return 1;
	}
	
	// Check if there is only 1 or 2 option left --> winner! 
	if(count($AV_result) <= 2) return 1;
	
	return 0;
}

function drop_loser_AV(&$AV_result, $answer_order, &$loser_comp){
	$loser = array_keys($AV_result, min($AV_result));
	if(count($loser) == count($AV_result)) return 0; // No winner. They are all losers!
	
	foreach($loser AS $i_inc => $id_option_loser){
		$loser_comp[] = $id_option_loser;
	}

	// Distribute loser(s) second vote
	foreach($loser AS $i_inc => $id_option_loser){
		unset($AV_result[$id_option_loser]); // Unset Loser
		
		// Distribute his vote
		$count_next_vote = 0;
		foreach($answer_order AS $data_answer){
			foreach($data_answer AS $vote_order => $id_option){
				if($count_next_vote == 1){
					// If this option is not already unset
					if(isset($AV_result[$id_option])) $AV_result[$id_option] += 1;
					$count_next_vote = 0;
				}
				
				// If previous options candidate is a loser, his next vote is counted
				if(($vote_order == 0 || ($vote_order == 1 && in_array($data_answer[0], $loser_comp))) && $id_option == $id_option_loser){
				// if($vote_order == 0 && $id_option == $id_option_loser){
					$count_next_vote = 1;
				}
				
				
			}
		}
	}
	
	return $loser;
}

function is_majority_winner($array_result, $nbr_total_vote){
	foreach($array_result AS $id_option => $nbr_vote){
		if($nbr_vote > ($nbr_total_vote / 2)) return 1;
	}
	
	return 0;
}

function drop_loser_supplementary(&$supplementary_result, $answer_order){
	$result_copy = $supplementary_result;
	
	$list_winner = []; // Find 2 winners, or more if candidates are equal
	$winner1 = array_keys($result_copy, max($result_copy));
	if(count($winner1) >= 2){ // If 2 winners or more
		foreach($winner1 AS $inc_i => $id_option){
			$list_winner[] = $id_option;
			unset($result_copy[$id_option]);
		}
	}else{
		unset($result_copy[$winner1[0]]);
		$winner2 = array_keys($result_copy, max($result_copy));
		$list_winner[] = $winner1[0];
		foreach($winner2 AS $inc_i => $id_option){
			$list_winner[] = $id_option;
			unset($result_copy[$id_option]);
		}
	}
	
	$list_loser = []; // Find the loser
	foreach($result_copy AS $id_option => $score){
		$list_loser[] = $id_option;
	}
	
	// Distribute loser(s) second vote
	foreach($list_loser AS $i_inc => $id_option_loser){
		unset($supplementary_result[$id_option_loser]); // Unset Loser
		
		// Distribute his vote
		$count_next_vote = 0;
		foreach($answer_order AS $data_answer){
			foreach($data_answer AS $vote_order => $id_option){
				if($count_next_vote == 1){
					// If this option is not already unset
					if(isset($supplementary_result[$id_option])) $supplementary_result[$id_option] += 1;
					$count_next_vote = 0;
				}
				
				// If previous options candidate is a loser, his next vote is counted
				if($vote_order == 0 && $id_option == $id_option_loser){
					$count_next_vote = 1;
				}
				
			}
		}
	}
	
}


function show_poll_for_list($poll_info){
	$poll_info["question"] = protect_xss($poll_info["question"]);
	$poll_info["url"] = protect_xss($poll_info["url"]);
	
	$html_string = "";
		$maxLength = 60;
		if(strlen($poll_info["question"]) > $maxLength){
			$question_crop = substr($poll_info["question"], 0, $maxLength-3)."...";
		}else $question_crop = $poll_info["question"];
		 
		$html_string .= "<li><a title='".$poll_info["question"]."' href='v/".$poll_info["url"]."'> ".$question_crop." </a></li>";
	
	
	return $html_string; 
}

function show_404_page($origin){
	$html_string = "";
	
	$html_string .= "<div class='container'>";
		$html_string .= show_main_title("404");
		$html_string .= "<div class='div_main_border col-xs-10 col-xs-offset-1 col-md-8 col-md-offset-2 col-lg-6 col-lg-offset-3'>";	
			$html_string .= "<h2 style='margin: 10px 0; padding-bottom: 5px; display:block; float:left; border-bottom: 2px solid #424247; color: #424247;'>404 Page Not Found</h2>";	
			$html_string .= "<div class='clear'></div>";	
			
			if($origin == "poll") $html_string .= "<p style='font-size:1.25rem; margin-top:20px; '>This poll doesn't exist.</p>";	
			else $html_string .= "<p style='font-size:1.25rem; margin-top:20px; '>This page doesn't exist.</p>";	
		$html_string .= "</div>";	
	$html_string .= "</div>";	
	
	return $html_string;
}

function show_main_title($page){
	$html_string = "<h1 id='main_title' class='main_title'><a href='./'> Create a Poll <br> And get the results with <span style='color:#fdaf48;'>multiple</span> voting systems! </a></h1>";
	
	return $html_string; 
}

// A "safer" session_start();
function safeSession() {
	if(isset($_COOKIE[session_name()]) AND preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $_COOKIE[session_name()])){
		session_start();
	}elseif(isset($_COOKIE[session_name()])){
		unset($_COOKIE[session_name()]);
		session_start(); 
	}else{
		session_start(); 
	}
}


function get_url(){
	global $PDO;
	
	$found_id = false;
	while($found_id == false){
		$random_url = rand(10000000, 99999999);
		$stmt = $PDO->prepare("SELECT url FROM poll WHERE url LIKE '%".$random_url."%' ");
		$stmt->execute();
		$exist = $stmt->fetch(PDO::FETCH_ASSOC);

		// If url doesnt exist
		if(!isset($exist["url"])) $found_id = true;
	}
	
	return $random_url;	
}


function get_private_url(){
	global $PDO;
	
	$found_id = false;
	while($found_id == false){
		$random_url = md5(uniqid(rand(), true));
		$random_url = substr($random_url, 0, 8); 
		$stmt = $PDO->prepare("SELECT url FROM poll WHERE url LIKE '%".$random_url."%' ");
		$stmt->execute();
		$exist = $stmt->fetch(PDO::FETCH_ASSOC);
		
		// If url doesnt exist
		if(!isset($exist["url"])) $found_id = true;
	}
	
	return $random_url;	
}


function protect_xss($string){
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}



function write_log($file, $line, $msg) {
	if(is_array($msg)) $msg = print_r($msg, true);
	
	// Pour avoir les millisecondes
	$t = microtime(true);
	$micro = sprintf("%06d",($t - floor($t)) * 1000000);
	$d = new DateTime( date('Y-m-d H:i:s.'.$micro,$t) );
	
	if($fh = fopen("log/".date("Y-m-d").".log", "a")) {
		fwrite($fh, sprintf("%s\t%s [%d]\t: %s\n", $d->format("Y-m-d H:i:s.u"), $file, $line, $msg));
		fclose($fh);
		return true;
	}
	return false;
}
?>	

