
mw.bloglist = function (option, callback) {
	var count, exchar, mode, user, pageImageFinished, extractsFinished;
	count = option.count || '5';
	exchars = option.exchars || '100';
	mode = option.mode ;
	user = option.user || "";
	var data = {
		recentChanges: [],
	};
	var myTemplate = mw.template.get('ext.blogPage.bloglist', 'bloglist.mustache');
	
	var params = {
		action:"query",
		list:"recentchanges", 
		rcnamespace:"500",
		rcshow:"!redirect",
		rclimit: count ,
		rctype:"new",
		rcprop:"user|timestamp|title",
		format:"json",
	}
	if (user !== ""){
		params.rcuser = user;
	}
	$.ajax( {
		url: '/api.php',
		data: params,
		type: 'POST',
		success: function(data1) {
			var theData = data1.query.recentchanges;
			var modeList ='<ul class="bloglist">'; 
			for (var i = 0; i < theData.length; i++) {
	 			data.recentChanges[i] = {
	 				'heading': theData[i].title,
	 				'author': theData[i].user,
	 				'timestamp': new Date(Date.parse(theData[i].timestamp)).toLocaleString(),
					'url':  encodeURIComponent(theData[i].title)
	 			};
				modeList += '<li><a href="' + encodeURIComponent(theData[i].title) + '">' + theData[i].title + '</a></li>' ;
			}
			modeList +='</ul>';
			if (mode==='list'){
				callback($(modeList));
				return ;
			}
			//console.log(data.recentChanges);
			var titles = new Array();
			for (var j = 0; j < theData.length; j++){
				titles[j] = theData[j].title;
			}
			rcTitles = titles.join('|'); 
			
			$.ajax( {
				url: '/api.php',
				data: {
					action: "query",
					prop: "pageimages", 
					pilimit: count,
					titles: rcTitles,
					format: "json"
				},
				type: 'POST',
				success: function(data2) {
					console.log(data2);
					var theData2 = data2.query.pages;
					//console.log(theData2);
					for (var key in theData2){
						
						for (var i in data.recentChanges ){
							if (data.recentChanges[i].heading === theData2[key]['title'] ){
								if (theData2[key].thumbnail){
									data.recentChanges[i].image = theData2[key].thumbnail.source;
								}else{
									data.recentChanges[i].image = 'http://fs.huijiwiki.com/www/resources/assets/Artboard%201.png';
								}
								data.recentChanges[i].imageAlt = theData2[key].pageimage;
							}
						}
					}
					if (extractsFinished){
						$html = myTemplate.render({'recentChanges' : data.recentChanges});
						callback($html);
					} else {
						pageImageFinished = true;
					}

				}
			});
			$.ajax( {
				url: '/api.php',
				data: {
					action: "query",
					prop: "extracts", 
					exchars: exchars,
					explaintext: "", 
					titles: rcTitles,
					exintro: "",
					exlimit: count,
					format: "json"
				},
				type: 'POST',
				success: function(data3) {
					console.log(data3);
					var theData3 = data3.query.pages;
					for (var key in theData3){
						for (var i in data.recentChanges ){
							if (data.recentChanges[i].heading === theData3[key]['title'] ){
								data.recentChanges[i].content = theData3[key].extract;
							}
						}
					}
					if (pageImageFinished){
						$html = myTemplate.render({'recentChanges' : data.recentChanges});
						callback($html);
					} else {
						extractsFinished = true;
					}
				}
			});
		}
	} );
}
