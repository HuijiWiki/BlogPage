<?php
/**
 * All BlogPage's hooked functions. These were previously scattered all over
 * the place in various files.
 *
 * @file
 */
class BlogPageHooks {

	/**
	 * Calls BlogPage instead of standard Article for pages in the NS_BLOG
	 * namespace.
	 *
	 * @param Title $title
	 * @param Article|BlogPage $article Instance of Article that we convert into a BlogPage
	 * @return bool
	 */
	public static function blogFromTitle( &$title, &$article ) {
		global $wgHooks, $wgOut, $wgRequest, $wgSupressPageTitle, $wgSupressSubTitle, $wgSupressPageCategories;
		if ( $title->getNamespace() == NS_BLOG ) {
			if ( !$wgRequest->getVal( 'action' ) ) {
				$wgSupressPageTitle = true;
			}

			$wgSupressSubTitle = true;
			$wgSupressPageCategories = true;

			// This will suppress category links in SkinTemplate-based skins
			$wgHooks['SkinTemplateOutputPageBeforeExec'][] = function( $sk, $tpl ) {
				$tpl->set( 'catlinks', '' );
				return true;
			};

			// $wgOut->enableClientCache( false );

			// Add CSS
			$wgOut->addModuleStyles( 'ext.blogPage' );

			$article = new BlogPage( $title );
		}

		return true;
	}
	public static function onSkinGetSub( $title, &$res, $context ){
		global $wgUser;
		if ( $title->exists() && $title->getNamespace() == NS_BLOG ) {
			$firstRev = $title->getFirstRevision();
			$authorId = $firstRev->getUser();
			$author = User::newFromId($authorId);
            $linkAttr = array('class' => 'mw-ui-anchor mw-ui-progressive mw-ui-quiet', 'rel'=>'nofollow');
            $editorAttr = array('class' => 'mw-ui-anchor mw-ui-progressive mw-ui-quiet mw-userlink', 'rel'=>'nofollow');
        	$authorLink = Linker::linkKnown($author->getUserPage(), $author->getName(), $editorAttr);
        	$bjtime = strtotime( $firstRev->getTimestamp() ) + 8*60*60;
        	$createtime = HuijiFunctions::getTimeAgo( $bjtime );
        	$diff = SpecialPage::getTitleFor('Diff',$firstRev->getId());
        	if (self::isOriginalWork( $title )){
 				$diffLink = Linker::LinkKnown($diff,'原创于',$linkAttr);
        	} else {
        		$diffLink = Linker::LinkKnown($diff,'发布于',$linkAttr);
        	}
            $thankLink = '';
            if ( class_exists( 'EchoNotifier' )
                && $wgUser->isLoggedIn() 
                && $wgUser->getId() !== $authorId
            ) {
                // Load the module for the thank links
                $thankLink .= '（<a class="mw-ui-anchor mw-ui-progressive mw-ui-quiet mw-thanks-thank-link" data-revision-id="'
                    .$title->getFirstRevision()->getId().'" href="javascript:void(0);">'.wfMessage('thanks-thank').'</a>）';
            }
            //Make it a td to reuse ext.thank.revthank   
            $res.= $authorLink.'&nbsp'.$diffLink.$createtime.'前'.$thankLink;
       		return false;
		}
	}
	public static function isOriginalWork( $title ){
		global $wgMemc;
		// $res = PageProps::getInstance()->getProperties($title, 'originalwork');
		// if (isset($res[$title->getArticleId()]) && $res[$title->getArticleId()] == '1' ){
		// 	return true;
		// } else {
		// 	return false;
		// }
		$key = wfMemcKey('BlogPage', 'isOriginalWork', $title->getPrefixedText());
		$result = $wgMemc->get($key);
		if ($result != ''){
			return $result;
		} else {
			$id = $title->getLatestRevID();
			$dbr = wfGetDB('DB_SLAVE');
			$res = $dbr->select(
					'change_tag',
					'ct_tag',
					[ 'ct_rev_id' => $id  ],
					__METHOD__
				);
			foreach ($res as $tag) {
				if ($tag->ct_tag == 'originalwork'){
					return true;
				}
			}
			return false;			
		}

	}
	public static function onSkinTemplateNavigation_Universal( &$sktemplate, &$links ) {
		global $wgUser;
		$ns = $sktemplate->getSkin()->getTitle()->getNamespace();
		//print_r($links);
		if ( $sktemplate->getSkin()->getTitle()->exists() && $ns == NS_BLOG && $wgUser->getId() != $sktemplate->getSkin()->getTitle()->getFirstRevision()->getUser()){
			unset($links['views']['edit']);
			unset($links['views']['ve-edit']);
		}
		// $action = $request->getText( 'action' );
		// $links['views']['chat'] = array(
		// 	'class' => ( $action == 'chat') ? 'selected' : false,
		// 	'text' => "Chat",
		// 	'href' => $sktemplate->makeArticleUrlDetails(
		// 		$sktemplate->getTitle()->getFullText(), 'action=chat' )['href']
		// );
		return true;		
	}
	/**
	 * Checks that the user is logged is, is not blocked via Special:Block and has
	 * the 'edit' user right when they're trying to edit a page in the NS_BLOG NS.
	 *
	 * @param EditPage $editPage
	 * @return bool True if the user should be allowed to continue, else false
	 */
	public static function allowShowEditBlogPage( $editPage ) {
		$context = $editPage->getArticle()->getContext();
		$output = $context->getOutput();
		$user = $context->getUser();

		if ( $editPage->mTitle->getNamespace() == NS_BLOG ) {
			if ( $user->isAnon() ) { // anons can't edit blog pages
				if ( !$editPage->mTitle->exists() ) {
					$output->addWikiMsg( 'blog-login' );
				} else {
					$output->addWikiMsg( 'blog-login-edit' );
				}
				return false;
			}
			if ( !$user->isAllowed( 'edit' ) || $user->isBlocked() ) {
				$output->addWikiMsg( 'blog-permission-required' );
				return false;
			}
			if ( $user->getId() != $editPage->mTitle->getFirstRevision()->getUser() || !$user->isAllowed('edit-others-blog') ){
				$output->addWikiMsg( 'blog-not-your-post' );
				return false;
			}


		}

		return true;
	}
	public static function onEditPageBeforeEditChecks(&$editpage, &$checkboxes, &$tabindex ){
		global $wgUseMediaWikiUIEverywhere;
		$originalLabel = wfMessage('originalwork')->parse();
		$checkboxes['original'] = '';
		if ( $editpage->getTitle()->getNamespace() == NS_BLOG ){
			$attribs = [
				'tabindex' => ++$tabindex,
				'accesskey' => wfMessage( 'accesskey-original' )->text(),
				'id' => 'hpChangeTags',
				'value' => 'originalwork'
			];
			if (self::isOriginalWork($editpage->getTitle())){
				$attribs['checked'] = 'checked';
			}
			$originalHtml = 
				Xml::check( 'hpChangeTags', false, $attribs ) .
				"&#160;<label for='hpChangeTags' id='mw-editpage-original'" .
				Xml::expandAttributes( [ 'title' => Linker::titleAttrib( 'original', 'withaccess' ) ] ) .
				">{$originalLabel}</label>";
			if ( $wgUseMediaWikiUIEverywhere ) {
                $checkboxes['original'] = Html::openElement( 'div', [ 'class' => 'mw-ui-checkbox' ] ) .
                    $originalHtml .
                    Html::closeElement( 'div' );
            } else {
                $checkboxes['original'] = $originalHtml;
            }				
		}  
	}

	/**
	 * RecentChange_save hook handler that tags staff edits as such when
	 * requested.
	 *
	 * @param RecentChange $rc
	 * @return bool
	 */
	public static function onRecentChange_save( RecentChange $rc ) {
		global $wgRequest, $wgMemc;
		if ( !$rc->getPerformer()->isAllowed( 'edit-others-blog' ) ) {
			return true;
		}
		$addTag = $wgRequest->getVal( 'hpChangeTags' ) === 'originalwork';
		$source = $rc->getAttribute( 'rc_source' );
		// Only apply the tag for edits, nothing else, and only if we were given
		// a tag to apply (!)
		if ( in_array( $source, array( RecentChange::SRC_EDIT, RecentChange::SRC_NEW ) ) ) {
			if ($addTag){
				$rcId = $rc->getAttribute( 'rc_id' );
				$revId = $rc->getAttribute( 'rc_this_oldid' );
				// In the future we might want to support different
				// types of staff edit tags
				ChangeTags::addTags( 'originalwork', $rcId, $revId );
			}
			$key = wfMemcKey('BlogPage', 'isOriginalWork', $rc->getTitle()->getPrefixedText());
			$wgMemc->delete($key);
		}
		return true;
	}

    public static function onRegisterTags( array &$tags ) {
        $tags[] = 'originalwork';
        return true;
    }
	public static function AssignAuthor( $user, &$aRights ) {
		global $wgTitle;
		// don't assign author to anons... messes up logging stuff.
		// plus it's all user_id based so it is impossible to differentiate one anon from another
		if (!$wgTitle || $wgTitle->getNamespace() != NS_BLOG ){
			return true;
		}
		if ($wgTitle->getFirstRevision() == null){
			$aRights[] = 'edit-others-blog';
			$aRights[] = 'applychangetags';
			$aRights = array_unique( $aRights );
			return true;			
		}
		if ( $user->getId() == $wgTitle->getFirstRevision()->getUser() ) {
			$aRights[] = 'edit-others-blog';
			$aRights[] = 'applychangetags';
			$aRights = array_unique( $aRights );
		}
		return true;
	}
	// public static function onChangeTagCanCreate( $tag, $user, &$canCreateResult){
	// 	if ($tag === 'originalwork'){
	// 		$canCreateResult == 
	// 	}
	// }
	/**
	 * This function was originally in the UserStats directory, in the file
	 * CreatedOpinionsCount.php.
	 * This function here updates the stats_opinions_created column in the
	 * user_stats table every time the user creates a new blog post.
	 *
	 * This is hooked into two separate hooks (todo: find out why), ArticleSave
	 * and ArticleSaveComplete. Their arguments are mostly the same and both
	 * have $article as the first argument.
	 *
	 * @param Article $article Article object representing the page that was/is
	 *                         (being) saved
	 * @return bool
	 */
	public static function updateCreatedOpinionsCount( &$article, &$user ) {
		$aid = $article->getTitle()->getArticleID();
		$u = $article->getTitle()->getFirstRevision()->getUser();
		$user_name = User::nameFromId($u);
		// Shortcut, in order not to perform stupid queries (cl_from = 0...)
		if ( $aid == 0 ) {
			return true;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'categorylinks',
			'cl_to',
			array( 'cl_from' => $aid ),
			__METHOD__
		);

		foreach ( $res as $row ) {
			$ctg = Title::makeTitle( NS_CATEGORY, $row->cl_to );
			$ctgname = $ctg->getText();
			$userBlogCat = wfMessage( 'blog-by-user-category' )->inContentLanguage()->text();

			// @todo CHECKME/FIXME: This probably no longer works as intended
			// due to the recent (as of 20 September 2014) i18n message change
			if ( strpos( $ctgname, $userBlogCat ) !== false ) {
				$user_name = trim( str_replace( $userBlogCat, '', $ctgname ) );
				$u = User::idFromName( $user_name );

				if ( $u ) {
					$stats = new UserStatsTrack( $u, $user_name );
					$userBlogCat = wfMessage( 'blog-by-user-category', $stats->user_name )
						->inContentLanguage()->text();
					// Copied from UserStatsTrack::updateCreatedOpinionsCount()
					// Throughout this code, we could use $u and $user_name
					// instead of $stats->user_id and $stats->user_name but
					// there's no point in doing that because we have to call
					// clearCache() in any case
					if ( !$user->isAnon() && $stats->user_id ) {
						$parser = new Parser();
						$ctgTitle = Title::newFromText(
							$parser->preprocess(
								trim( $userBlogCat ),
								$article->getContext()->getTitle(),
								$article->getContext()->getOutput()->parserOptions()
							)
						);
						$ctgTitle = $ctgTitle->getDBkey();
						$dbw = wfGetDB( DB_MASTER );

						$opinions = $dbw->select(
							array( 'page', 'categorylinks' ),
							array( 'COUNT(*) AS CreatedOpinions' ),
							array(
								'cl_to' => $ctgTitle,
								'page_namespace' => NS_BLOG // paranoia
							),
							__METHOD__,
							array(),
							array(
								'categorylinks' => array(
									'INNER JOIN',
									'page_id = cl_from'
								)
							)
						);

						// Please die in a fire, PHP.
						// selectField() would be ideal above but it returns
						// insane results (over 300 when the real count is
						// barely 10) so we have to fuck around with a
						// foreach() loop that we don't even need in theory
						// just because PHP is...PHP.
						$opinionsCreated = 0;
						foreach ( $opinions as $opinion ) {
							$opinionsCreated = $opinion->CreatedOpinions;
						}

						$res = $dbw->update(
							'user_stats',
							array( 'stats_opinions_created' => $opinionsCreated ),
							array( 'stats_user_id' => $stats->user_id ),
							__METHOD__
						);

						$stats->clearCache();
					}
				}
			}
		}

		return true;
	}

	public static function incrOpinionCount($wikiPage, User $user, $content, $summary, $isMinor,$isWatch, $section, $flags, Revision $revision){
		global $wgContLang;
		if ( $wikiPage->getTitle()->getNamespace() == NS_BLOG ){
			$stats = new UserStatsTrack( $user->getId(), $user->getName() );
			$stats->incStatField( 'opinions_created' );	
			// $localizedCategoryNS = $wgContLang->getNsText( NS_CATEGORY );	
			// $today = $wgContLang->date( wfTimestampNow() );
			// // The blog post will be by default categorized into two
			// // categories, "Articles by User $1" and "(today's date)",
			// // but the user may supply some categories themselves, so
			// // we need to take those into account, too.
			// $categories = array(
			// 	'[[' . $localizedCategoryNS . ':' .
			// 		wfMessage(
			// 			'blog-by-user-category',
			// 			$user->getName()
			// 		)->inContentLanguage()->text() .
			// 	']]' . "\n" .
			// 	"[[{$localizedCategoryNS}:{$today}]]"
			// );
			// // Convert the array into a string
			// $text = ContentHandler::getContentText($content);
			// $wikitextCategories = implode( "\n", $categories );
			
			// // Perform the edit
			// $newContent = ContentHandler::makeContent($text."\n".$wikitextCategories."\n__NOEDITSECTION__\n__NOTOC__", $wikiPage->getTitle());
			// $wikiPage->doEditContent(
			// 	$newContent,
			// 	wfMessage( 'blog-create-summary' )->inContentLanguage()->text(),
			// 	0,
			// 	false,
			// 	$user
			// );
		} 
	}
	public static function onPageContentSave( &$wikiPage, &$user, &$content, &$summary, 
		$isMinor, $isWatch, $section, &$flags, &$status ) 
	{ 
		global $wgContLang;
		if ($wikiPage->getTitle()->getNamespace() == NS_BLOG && !$wikiPage->getTitle()->exists()){
			$localizedCategoryNS = $wgContLang->getNsText( NS_CATEGORY );	
			$today = $wgContLang->date( wfTimestampNow() );
			// The blog post will be by default categorized into two
			// categories, "Articles by User $1" and "(today's date)",
			// but the user may supply some categories themselves, so
			// we need to take those into account, too.
			$categories = array(
				'[[' . $localizedCategoryNS . ':' .
					wfMessage(
						'blog-by-user-category',
						$user->getName()
					)->inContentLanguage()->text() .
				']]' . "\n" .
				"[[{$localizedCategoryNS}:{$today}]]"
			);
			// Convert the array into a string
			$text = ContentHandler::getContentText($content);
			$wikitextCategories = implode( "\n", $categories );
			
			// Perform the edit
			$newContent = ContentHandler::makeContent($text."\n".$wikitextCategories."\n__NOEDITSECTION__\n__NOTOC__", $wikiPage->getTitle());	
			$content = $newContent;		
		}

	}
	public static function decrOpinionCount( &$article, User &$user, $reason, &$error, &$status, $suppress ) { 
		if ( $article->getTitle()->getNamespace() == NS_BLOG ){
			$origUserId = $article->getTitle()->getFirstRevision()->getUser();
			$stats = new UserStatsTrack( $origUserId );
			$stats->decStatField( 'opinions_created' );			
		} 		
	}

	public static function onPreloadGetInput( &$preload, &$html){
		$html .= "<option id='create-blog' value='' prefix='Blog:'>博客</option>";
	}
	/**
	 * Show a list of this user's blog articles in their user profile page.
	 *
	 * @param UserProfilePage $userProfile
	 * @return bool
	 */
	public static function getArticles( $userProfile ) {
		global $wgUserProfileDisplay, $wgMemc, $wgOut;

		if ( !$wgUserProfileDisplay['articles'] ) {
			return true;
		}

		$user_name = $userProfile->user_name;
		$output = '';

		// Try cache first
		$key = wfMemcKey( 'user', 'profile', 'articles', $userProfile->user_id );
		$data = $wgMemc->get( $key );
		$articles = array();

		if ( $data != '' ) {
			wfDebugLog(
				'BlogPage',
				"Got UserProfile articles for user {$user_name} from cache\n"
			);
			$articles = $data;
		} else {
			wfDebugLog(
				'BlogPage',
				"Got UserProfile articles for user {$user_name} from DB\n"
			);
			$categoryTitle = Title::newFromText(
				wfMessage(
					'blog-by-user-category',
					$user_name
				)->inContentLanguage()->text()
			);
			if( $categoryTitle == null ){
				return true;
			}

			$dbr = wfGetDB( DB_SLAVE );
			/**
			 * I changed the original query a bit, since it wasn't returning
			 * what it should've.
			 * I added the DISTINCT to prevent one page being listed five times
			 * and added the page_namespace to the WHERE clause to get only
			 * blog pages and the cl_from = page_id to the WHERE clause so that
			 * the cl_to stuff actually, y'know, works :)
			 */
			$res = $dbr->select(
				array( 'page', 'categorylinks' ),
				array( 'DISTINCT page_id', 'page_title', 'page_namespace' ),
				/* WHERE */array(
					'cl_from = page_id',
					'cl_to' => array( $categoryTitle->getDBkey() ),
					'page_namespace' => NS_BLOG
				),
				__METHOD__,
				array( 'ORDER BY' => 'page_id DESC', 'LIMIT' => 5 )
			);

			foreach ( $res as $row ) {
				$articles[] = array(
					'page_title' => $row->page_title,
					'page_namespace' => $row->page_namespace,
					'page_id' => $row->page_id
				);
			}

			$wgMemc->set( $key, $articles, 60 );
		}

		// Load opinion count via user stats;
		$stats = new UserStats( $userProfile->user_id, $user_name );
		$stats_data = $stats->getUserStats();
		$articleCount = $stats_data['opinions_created'];

		$articleLink = Title::makeTitle(
			NS_CATEGORY,
			wfMessage(
				'blog-by-user-category',
				$user_name
			)->inContentLanguage()->text()
		);

		if ( count( $articles ) > 0 ) {
			$output .= '<div class="panel panel-primary darken no-border"><div class="user-section-heading panel-heading">
				<div class="user-section-title">' .
					wfMessage( 'blog-user-articles-title' )->escaped() .
				'</div>
				<div class="user-section-actions">
					<div class="action-right">';
			if ( $articleCount > 5 ) {
				$output .= '<a href="' . htmlspecialchars( $articleLink->getFullURL() ) .
					'" rel="nofollow">' . wfMessage( 'user-view-all' )->escaped() . '</a>';
			}
			$output .= '</div>
					<div class="action-left">' .
					wfMessage( 'user-count-separator' )
						->numParams( count( $articles ), $articleCount )
						->escaped() . '</div>
					<div class="clearfix"></div>
				</div>
			</div>
			<div class="panel-body user-articles-container">';



			foreach ( $articles as $article ) {
				$articleTitle = Title::makeTitle(
					$article['page_namespace'],
					$article['page_title']
				);
				$voteCount = BlogPage::getVotesForPage( $article['page_id'] );
				$commentCount = BlogPage::getCommentsForPage( $article['page_id'] );
				$divClass = 'activity-item';
				$output .= '<div class="' . $divClass . "\">".
					'<span class="article-title"><i class="fa fa-rss-square" aria-hidden="true"></i>
						<a href="' . htmlspecialchars( $articleTitle->getFullURL() ) .
							"\">{$articleTitle->getText()}</a>
						<span class=\"item-small secondary\">" .
							wfMessage( 'blog-user-article-comment' )
								->numParams( $commentCount )
								->text() . "</span><span class=\"item-small secondary\">" .
							wfMessage( 'blog-user-article-votes' )
								->numParams( $voteCount )
								->text() . '</span>
					</span>
				</div>';

			}

			$output .= '</div></div>';
		}

		$wgOut->addHTML( $output );

		return true;
	}

	/**
	 * Register the canonical names for our namespace and its talkspace.
	 *
	 * @param array $list Array of namespace numbers with corresponding
	 *                     canonical names
	 * @return bool
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_BLOG] = 'Blog';
		$list[NS_BLOG_TALK] = 'Blog_talk';
		return true;
	}
}
