$(document).ready(function(){
	mw.loader.using('ext.blogPage.bloglist').done(function(){
		$(".bloglist-container").each(function(){
			var $this = $(this);
			mw.bloglist(
				{
					count: $this.data('count'),
					mode: $this.data('mode'),
					user: $this.data('user')
				}, 
				function(html){
					$this.append(html);
				}
			);
		});

	});
});
