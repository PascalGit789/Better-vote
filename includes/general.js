function form_add_input(){
	if($(".form_option").last().val() > ""){
		var new_option_id = +$(".form_option").last().attr("id").split('_')[2] + 1;
		if(new_option_id > 10) return 0;
		$(".form_p_option").append("<input type='text' id='form_option_"+new_option_id+"' class='form_option' placeholder='"+new_option_id+"..' maxlength='75' onKeyUp='form_add_input()'>");
	}
}

$(".form_vote_checkbox").change(function() {
	if($(this).prop('checked') == true){ // On check
		var highest_value = 0;
		$(".form_vote_div_order").each(function( index ) {
			if(highest_value < $(this).html()) highest_value = $(this).html();
		});
		highest_value++;
		$(this).prev(".form_vote_div_order").html(highest_value);
	}else{ // On uncheck
		var order_value_uncheck = $(this).prev(".form_vote_div_order").html();
		$(this).prev(".form_vote_div_order").html("");
		
		$(".form_vote_div_order").each(function( index ) {
			if($(this).html() > order_value_uncheck){
				var inc_order = $(this).html();
				inc_order--;
				$(this).html(inc_order);
			}
		});
	}
});
 
// Mostly for Chrome. When user vote and then back on vote page, checkbox are still checked
$(".form_vote input[type='checkbox']").each(function( index ) {
	if($(this).prop('checked') == true && !$.trim($(this).prev(".form_vote_div_order").html()).length){
		$(this).prop('checked', false);
	}
});

$("#form_create_btn_create").click(function() {
	// JS validation
	if(!$.trim($("#form_question").val()).length) {
		alert("Type a question!");
		return false;
	}
	
	var options = {};
	var size = 0;
	$(".form_option").each(function(i) {
		if($(this).val() > ""){
			options[i] = $(this).val();
			size++;
		}
	});
	
	if(size < 2){
		alert("2 options are required");
		return false;
	}

	if($("#form_checkbox_public").prop("checked")) var is_public = 1;
	else is_public = 0;
	
	if($("#form_checkbox_IP").prop("checked")) var is_restricted_ip = 1;
	else is_restricted_ip = 0;
	
	$.ajax({
		type:"POST",
		cache:false,
		url:"jx.php?action=create_poll",
		data:"question="+$("#form_question").val()+"&options="+JSON.stringify(options)+"&public="+is_public+"&is_restricted_ip="+is_restricted_ip,
		complete:function(jqXHR, textStatus){
			var ajax_answer = jqXHR.responseText.trim();
			if(ajax_answer != 0 && ajax_answer != 2 && ajax_answer.length == 8){
				window.location = "/Better_vote/v/"+ajax_answer;
			}else if(ajax_answer == 2){
				alert("You have reach the maximum number of poll created for your IP. Email me if you want me to reset it (bettervote1@gmail.com). I'll do it if I see that you are not a bot.");
			}else{
				alert("You need to enter a question and 2 polls options");
			}
		}
	});
});

$("#form_vote_btn_vote").click(function() {
	var id_poll = $(".form_vote").attr("id").split("_")[2];
	
	var vote_order = {};
	$(".form_vote_div_order").each(function(i) {	
		if($(this).html() > ""){
			vote_order[$(this).html()] = $(this).attr("id").split("_")[3];
		}
	});
	
	$.ajax({
		type:"POST",
		cache:false,
		url:"jx.php?action=vote",
		data:"id_poll="+id_poll+"&vote_order="+JSON.stringify(vote_order),
		complete:function(jqXHR, textStatus){
			var ajax_answer = jqXHR.responseText.trim();
			if(ajax_answer == 1){
				var url = window.location.href;
				var url_poll = url.split('/').pop();
				window.location = "/Better_vote/r/"+url_poll;
			}else if(ajax_answer == 2){
				alert("This poll is restricted to one vote per IP. You already voted!");
			}
		}
	});
});

$("#form_search_btn").click(function() {
	// if($("#form_search_text").val().length < 3){
		// alert("Please insert more than 2 characters");
		// return false;
	// }
	$.ajax({
		type:"POST",
		cache:false,
		url:"jx.php?action=search",
		data:"search_text="+$("#form_search_text").val(),
		complete:function(jqXHR, textStatus){
			var ajax_answer = jqXHR.responseText.trim();
			if(ajax_answer == 0) return false;
			if($(".div_main_border_search").length){
				$(".div_main_border_search").remove();
				$("form.form_search").after(ajax_answer);
			}else{ // If first search
				$(".div_column_bottom ").remove();
				$("form.form_search").removeClass('col-md-offset-0').addClass('col-md-offset-17-5-pourcent');
				$("form.form_search").after(ajax_answer);
			}
		}
	});
});


$(".result_header_div").click(function() {
	$(this).find(".result_header_detail").toggle();
});

$(".form_btn_share").click(function() {
	alert("Share is not implemented yet.. You can just copy and paste the URL where you want.");
});

if($("#FPTP_pie").length){
	function show_pie_legend(chartInstance, name_chart){
		var highest_score = 0;
		var winner_key = null;
		for (var i = 0; i < chartInstance.data.datasets[0].data.length; i++) { // Find winner
			var score = chartInstance.data.datasets[0].data[i];
			if(highest_score < score){
				highest_score = score;
				winner_key = i;
			}
		}
		
		for (var i = 0; i < chartInstance.data.datasets[0].data.length; i++) { // Find if there is a tie  (2 winners)
			var score = chartInstance.data.datasets[0].data[i];
			if(highest_score == score && winner_key != i) winner_key = null;
		}
		
		var html_string = "";
		html_string += "<table class='pie_legend_table table_border'>";
		
		html_string += "<tr style='font-size:1.05rem;'>";
		html_string += "<th style='border: 3px solid #898989; border-bottom: 0;'>Candidate</th><th style='border: 3px solid #898989; border-bottom: 0;'>Score</th>";
		html_string += "</tr>";
			
		for (var i = 0; i < chartInstance.data.datasets[0].data.length; i++) {
			var score = chartInstance.data.datasets[0].data[i];
			if(score == 0 && (name_chart == 'AV' || name_chart == 'supplementary')) score = '-';
			
			if(winner_key == i) html_string += "<tr class='table_option_winner'>";
			else html_string += "<tr style=''>";
			
			html_string += "<td style='border:3px solid " + chartInstance.data.datasets[0].backgroundColor[i] + ";'>";
			if (chartInstance.data.labels[i]) {
				html_string += chartInstance.data.labels[i];
			}
			html_string += "</td>";
			html_string += "<td style='border:3px solid " + chartInstance.data.datasets[0].backgroundColor[i] + ";' class='pie_legend_score_span_"+name_chart+"'>"+score+"</td>";
			html_string += "</tr>";
		}
		html_string += "</table>";
		
		return html_string;
	}

	if($(".container").width() == "750"){
		var chart_bar_font_size = 17;
		var chart_pie_font_size = 19;
	}else{
		var chart_bar_font_size = 13;
		var chart_pie_font_size = 15;
	}
	
	// ALL VOTE
	var all_vote_label = [];
	var all_vote_result1 = [];
	var all_vote_result2 = [];
	var all_vote_result3 = [];
	var nbr_options = $(".all_vote_each").length;
	$(".all_vote_each").each(function(i) {	
		var label_text = $(this).find(".all_vote_texte").html();
		var max_length = 80 / nbr_options;
		
		if(label_text.length > max_length) label_text = label_text.substring(0, (max_length - 4)) + " ...";
		all_vote_label.push(label_text);
		all_vote_result1.push($(this).find(".all_vote_result_1").html());
		all_vote_result2.push($(this).find(".all_vote_result_2").html());
		all_vote_result3.push($(this).find(".all_vote_result_3").html());
	});
	$(".all_vote_result").remove();
	
	var ctxPTD = $("#chart_all_vote").get(0).getContext("2d");
	var chart_all_votes = new Chart(ctxPTD, {
			type: 'bar',
			data: {
					labels: all_vote_label,
					datasets: [{
							label: 'First',
							backgroundColor: "rgba(99, 255, 172,0.9)",
							data: all_vote_result1
					},{
							label: 'Second',
							backgroundColor: "rgba(248, 243, 47, 0.9)",
							data: all_vote_result2
					},{
							label: 'Third',
							backgroundColor: "rgba(255,99,132,0.9)",
							data: all_vote_result3
					}]
			},
			options: {
					scales: {
							yAxes: [{
									ticks: {
											beginAtZero:true,
											userCallback: function(label, index, labels) {
													if (Math.floor(label) === label) {
															return label;
													}

											},
											fontSize: chart_bar_font_size
									}
							}],
							xAxes: [{
								ticks: {
									fontSize: chart_bar_font_size
								}
							}],
					},
					responsive: true
			}
	});
				
	// General Config         - Same arrays in PHP  function show_result()    Need to change in both places
	var array_backgroundColor = 		 ["#5093ce", "#43404c", "#fab657", "#871887", "#56fefe", "#8d8d19", "#808080", "#eaaede","#800000", "#008000"];
	var array_hoverBackgroundColor = ["#78acd9", "#605c6a", "#fbcb88", "#973397", "#78ffff", "#97972e", "#b4afaf", "#ebbce2", "#9d2c2c", "#18b818"];
	
	
	// FOR FPTP CHART
	var FPTP_label = [];
	var FPTP_result = [];
	$(".FPTP_result_option").each(function(i) {	
		FPTP_label.push($(this).html());
		FPTP_result.push(+$(this).next().html());
	});
	$(".FPTP_result").remove();
	
	var config = {
		type: 'pie',
		data: {
			labels: FPTP_label,
			datasets: [{
				data: FPTP_result,
				backgroundColor: array_backgroundColor,
				hoverBackgroundColor: array_hoverBackgroundColor
			}]
		},
		options: {
			tooltips: {
				enabled: true,
			},
			legend:{
				display: false,
			},
			legendCallback: function(chartInstance) {
				return show_pie_legend(chartInstance, "FPTP");
			},
			pieceLabel: {
				mode: 'percentage',
				precision: 2,
				fontSize: chart_pie_font_size
			}
		}
	};
	var ctx = $("#chart_FPTP").get(0).getContext("2d");
	chart_FPTP = new Chart(ctx, config);
	
	$("#FPTP_pie").html(chart_FPTP.generateLegend());

	
	// FOR AV CHART
	var AV_label = [];
	var AV_result = [];
	$(".AV_result_option").each(function(i) {	
		AV_label.push($(this).html());
		AV_result.push(+$(this).next().html());
	});
	$(".AV_result").remove();
	
	var config = {
		type: 'pie',
		data: {
			labels: AV_label,
			datasets: [{
				data: AV_result,
				backgroundColor: array_backgroundColor,
				hoverBackgroundColor: array_hoverBackgroundColor
			}]
		},
		options: {
			tooltips: {
				enabled: true,
			},
			legend:{
				display: false,
			},
			legendCallback: function(chartInstance) {
				return show_pie_legend(chartInstance, "AV");
			},
			pieceLabel: {
				mode: 'percentage',
				precision: 2,
				fontSize: chart_pie_font_size
			}
		}
	};

	var ctx = $("#chart_AV").get(0).getContext("2d");
	chart_AV = new Chart(ctx, config);
	$("#AV_pie").html(chart_AV.generateLegend());
	
	
	// FOR APPROVAL CHART
	var approval_label = [];
	var approval_result = [];
	$(".approval_result_option").each(function(i) {	
		var label_text = $(this).html()
		
		approval_label.push(label_text);
		approval_result.push(+$(this).next().html());
	});
	$(".approval_result").remove();
	
	var config = {
		type: 'bar',
		data: {
			labels: approval_label,
			datasets: [{
				data: approval_result,
				backgroundColor: array_backgroundColor,
				hoverBackgroundColor: array_hoverBackgroundColor
			}]
		},
		options: {
			scales: {
				yAxes: [{
						ticks: {
								beginAtZero:true,
								userCallback: function(label, index, labels) {
										if (Math.floor(label) === label) {
												return label;
										}

								},
								fontSize: chart_bar_font_size
						}
				}],
				xAxes: [{
                display: false
				}]
			},
			tooltips: {
				enabled: true,
			},
			legend:{
				display: false,
			},
			legendCallback: function(chartInstance) {
				return show_pie_legend(chartInstance, "approval");
			},
			responsive: true
		}
	};
	var ctx = $("#chart_approval").get(0).getContext("2d");
	chart_approval = new Chart(ctx, config);
	
	$("#approval_pie").html(chart_approval.generateLegend());
	

	// FOR BORDA CHART
	var borda_label = [];
	var borda_result = [];
	$(".borda_result_option").each(function(i) {	
		var label_text = $(this).html()
		
		borda_label.push(label_text);
		borda_result.push(+$(this).next().html());
	});
	$(".borda_result").remove();
	
	var config = {
		type: 'pie',
		data: {
			labels: borda_label,
			datasets: [{
				data: borda_result,
				backgroundColor: array_backgroundColor,
				hoverBackgroundColor: array_hoverBackgroundColor
			}]
		},
		options: {
			tooltips: {
				enabled: true,
			},
			legend:{
				display: false,
			},
			legendCallback: function(chartInstance) {
				return show_pie_legend(chartInstance, "borda");
			},
			pieceLabel: {
				mode: 'percentage',
				precision: 2,
				fontSize: chart_pie_font_size
			}
		}
	};
	var ctx = $("#chart_borda").get(0).getContext("2d");
	chart_borda = new Chart(ctx, config);
	
	$("#borda_pie").html(chart_borda.generateLegend());
	
	// FOR SUPPLEMENTARY CHART
	var supplementary_label = [];
	var supplementary_result = [];
	$(".supplementary_result_option").each(function(i) {	
		supplementary_label.push($(this).html());
		supplementary_result.push(+$(this).next().html());
	});
	$(".supplementary_result").remove();
	
	var config = {
		type: 'pie',
		data: {
			labels: supplementary_label,
			datasets: [{
				data: supplementary_result,
				backgroundColor: array_backgroundColor,
				hoverBackgroundColor: array_hoverBackgroundColor
			}]
		},
		options: {
			tooltips: {
				enabled: true,
			},
			legend:{
				display: false,
			},
			legendCallback: function(chartInstance) {
				return show_pie_legend(chartInstance, "supplementary");
			},
			pieceLabel: {
				mode: 'percentage',
				precision: 2,
				fontSize: chart_pie_font_size
			}
		}
	};
	var ctx = $("#chart_supplementary").get(0).getContext("2d");
	chart_supplementary = new Chart(ctx, config);
	
	$("#supplementary_pie").html(chart_supplementary.generateLegend());
	
	
	// setInterval(function(){ 
		// refresh_chart();
	// }, 15000); // Auto refresh every 15 sec
	
	function refresh_chart(){
		var url = window.location.href;
		var url_poll = url.split('/').pop();
		
		$.ajax({
			type:"POST",
			cache:false,
			url:"jx.php?action=refresh_result",
			data:"url_poll="+url_poll,
			complete:function(jqXHR, textStatus){
				var ajax_answer =  jqXHR.responseText.trim();
				if(ajax_answer == 0) return false;
				ajax_answer =  $.parseJSON(ajax_answer);
				$.each(ajax_answer, function(key, value) {
					if(key == "condorcet"){
						$("#div_condorcet_result").html(value);
					}else if(key == "all_around_winnner"){
						$("#div_all_around_winner_result").html(value);
					}else{
						var segment_inc = 0;
						$.each(value, function(id_option, result) {
							if(key == "all_vote"){
								$.each(result, function(vote_order, score) {
									var i_array = vote_order - 1;
									if(chart_all_votes.data.datasets[i_array] !== undefined) chart_all_votes.data.datasets[i_array].data[segment_inc] = score;
								});
								
							}else if(key == "FPTP"){
								chart_FPTP.data.datasets[0].data[segment_inc] = result;
								$(".pie_legend_score_span_FPTP:eq("+segment_inc+")").html(result);
								
							}else if(key == "AV"){
								chart_AV.data.datasets[0].data[segment_inc] = result;
								if(+result > 0) $(".pie_legend_score_span_AV:eq("+segment_inc+")").html(result);
								else $(".pie_legend_score_span_AV:eq("+segment_inc+")").html("-");
							}else if(key == "approval"){
								chart_approval.data.datasets[0].data[segment_inc] = result;
								$(".pie_legend_score_span_approval:eq("+segment_inc+")").html(result);
							}else if(key == "borda"){
								chart_borda.data.datasets[0].data[segment_inc] = result;
								$(".pie_legend_score_span_borda:eq("+segment_inc+")").html(result);
							}else if(key == "supplementary"){
								chart_supplementary.data.datasets[0].data[segment_inc] = result;
								if(+result > 0) $(".pie_legend_score_span_supplementary:eq("+segment_inc+")").html(result);
								else $(".pie_legend_score_span_supplementary:eq("+segment_inc+")").html("-");
							}
							
							segment_inc++;
						});
					}
				});
				
				$(".pop_up_refresh").fadeIn();
				setTimeout(function(){ $(".pop_up_refresh").fadeOut(); }, 1200);	
				
				console.log("updated");
				chart_all_votes.update();
				chart_FPTP.update();
				chart_AV.update();
				chart_approval.update();
				chart_borda.update();
				chart_supplementary.update();
				
				// Update the winner class   (The bold and the small font increase)
				$(".pie_legend_table .table_option_winner").removeClass();
				
				var FPTP_position_winner = ajax_answer.winner_score.FPTP;
				if(FPTP_position_winner != null){
					FPTP_position_winner++;
					$("#FPTP_pie tr:eq("+FPTP_position_winner+")").addClass("table_option_winner");
				}
				
				var AV_position_winner = ajax_answer.winner_score.AV;
				if(AV_position_winner != null){
					AV_position_winner++;
					$("#AV_pie tr:eq("+AV_position_winner+")").addClass("table_option_winner");
				}
				
				var approval_position_winner = ajax_answer.winner_score.approval;
				if(approval_position_winner != null){
					approval_position_winner++;
					$("#approval_pie tr:eq("+approval_position_winner+")").addClass("table_option_winner");
				}
				
				var borda_position_winner = ajax_answer.winner_score.borda;
				if(borda_position_winner != null){
					borda_position_winner++;
					$("#borda_pie tr:eq("+borda_position_winner+")").addClass("table_option_winner");
				}
				
				var supplementary_position_winner = ajax_answer.winner_score.supplementary;
				if(supplementary_position_winner != null){
				 supplementary_position_winner++;
				 $("#supplementary_pie tr:eq("+supplementary_position_winner+")").addClass("table_option_winner");
				}
			}
		});
	}
}

function isScrolledIntoView(elem){
	var docViewTop = $(window).scrollTop();
	var docViewBottom = docViewTop + $(window).height();
	
	var elemTop = $(elem).offset().top;
	var elemBottom = elemTop + $(elem).height();

	return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
}

if($(".pop_up_just_voted").length){
	setTimeout(function(){ 
		$(".pop_up_just_voted").fadeIn(); 
		if(isScrolledIntoView($(".pop_up_just_voted"))){
			setTimeout(function(){ $(".pop_up_just_voted").fadeOut(); }, 1500);
		}else{ // For cellphone, when the pop up is not on screen at the beginning
			$(window).scroll(function (event) {
				if($(".pop_up_just_voted").length && isScrolledIntoView($(".pop_up_just_voted"))){
					setTimeout(function(){ $(".pop_up_just_voted").fadeOut(); }, 1500);
				}
			});
		}
	}, 150);
}

// For the wiki link in the result header. without that, the accordeon open/close on the wiki click
$('a').click(function(e) { e.stopPropagation(); });

$('.public_list_more').click(function(e) { 
	var public_list = '';
	if($(this).hasClass('public_list_most_voted')) public_list = 'most_voted';
	else if($(this).hasClass('public_list_trending')) public_list = 'trending';
	else if($(this).hasClass('public_list_new')) public_list = 'new';
	
	var list_size = $(this).closest('ol').find('li').length;
	list_size--;
	var that = this;
	if(list_size > 350) return false;
	$.ajax({
		type:"POST",
		cache:false,
		url:"jx.php?action=get_more_list",
		data:"public_list="+public_list+"&list_size="+list_size,
		complete:function(jqXHR, textStatus){
			var ajax_answer = jqXHR.responseText.trim();
			if(ajax_answer == 0){
				$(that).closest('li').remove();
			}else{
				$(that).closest('li').prev().after(ajax_answer);
				if(($(that).closest('ol').find('li').length-1) % 10 != 0) $(that).closest('li').remove();
				else if(list_size+10 >= 300)  $(that).closest('li').remove();
			}
		}
	});
});

function scrollToAnchor(div_id){
		var aTag = $("div[id='"+ div_id +"']");
		$('html,body').animate({scrollTop: aTag.offset().top},'slow');
}