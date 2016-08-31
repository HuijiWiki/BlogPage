<?php
/**
 * A special page that displays the 25 most recent blog posts.
 *
 * @file
 * @ingroup Extensions
 */
class ArticleLists extends IncludableSpecialPage {

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'ArticleLists' );
	}

	/**
	 * Show the new special page
	 *
	 * @param int $limit Show this many entries (LIMIT for SQL)
	 */
	public function execute( $par ) {
		global $wgMemc, $wgExtensionAssetsPath;
		$params = explode('/', $par);
		if (  isset($params[0]) && $params[0] != '' && in_array( $params[0], ['list', 'expanded']) ){
			$mode = $params[0];
		} else {
			$mode = 'expanded';
		}
		if ( isset($params[1]) && $params[1] != '' && is_integer($params[1]) && $params[1] <= 20){
			$count = $params[1];
		} else {
			$count = '5';
		}
		if ( isset($params[2]) && $params[2] != ''){
			$user = $params[2];
		} else {
			$user = null;
		}
		$out = $this->getOutput();
		$out->addModules('ext.blogPage.special.articlelist');
		$this->setHeaders();
		$out->addHtml('<div class="bloglist-container" data-mode="'.$mode.'" data-count="'.$count.'" data-user="'.$user.'"></div>');
		$out->addHtml( Linker::linkKnown( SpecialPage::getTitleFor('NewPages'), wfMessage('blogpage-see-all')->text(), ['class'=>'btn pull-right btn-primary'], ['namespace' => '500' ]  ) );
		// $out->addHTML( $output );
	}

	/**
	 * Group this special page under the correct header in Special:SpecialPages.
	 *
	 * @return string
	 */
	function getGroupName() {
		return 'blog';
	}

}