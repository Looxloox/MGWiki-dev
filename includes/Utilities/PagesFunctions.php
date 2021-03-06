<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

use Title;
use WikiPage;
use CommentStoreComment;
use WikitextContent;
use MovePage;
use MediaWiki\Extension\MGWikiDev\Classes\MGWStatus as Status;

/**
  * Ensemble de fonctions statiques sur les pages
  */
class PagesFunctions
{
  /**
   * @param int $page_id
   * @return bool
   */
  public static function pageID( $page_name ) {
    $title = Title::newFromText( $page_name );
    return $title->getArticleID();
  }

  /**
   * @param string $page_name
   * @return bool
   */
  public static function pageArchiveExists( $page_name ) {
    $title = Title::newFromText( $page_name );
    return $title->isDeletedQuick();
  }

  /**
    * @param string $pagename : titre de la page
    * @param int $namespace : constante de l'espace de nom de la page (ex.: 'NS_MAIN')
    * @param bool $check = false (return null if title does not exist)
    *
    * @return Title|null
    */
  public function getTitleFromText ( $pagename, $namespace = NS_MAIN, $check = false ) {
    $title = Title::newFromText( $pagename, $namespace );
    if ( $check && $title->getArticleID() <= 0 ) {
      return null;
    } else return $title;
  }

  /**
    * @param Title $title
    * @param bool $check = false (return null if page does not exist)
    *
    * @return WikiPage|null
    */
  public function getPageFromTitle ( Title $title, $check = false ) {
    if ( $check ) {
      if ( $title->getArticleID() <= 0 ) return null;
    }
    return WikiPage::factory( $title );
  }

  /**
    * @param int $id
    *
    * @return WikiPage|null
    */
  public function getPageFromId ( $id ) {
    return WikiPage::newFromID( $id );
  }

  /**
    * @param string $pagename : titre de la page
    * @param int $namespace : constante de l'espace de nom de la page (ex.: 'NS_MAIN')
    * @param bool $check = false (return null if title or page does not exist)
    *
    * @return WikiPage|null
    */
  public function getPageFromTitleText ( $pagename, $namespace = NS_MAIN, $check = false ) {
    $title = self::getTitleFromText( $pagename, $namespace, $check );
    if ( is_null( $title ) ) {
      return null;
    }
    return self::getPageFromTitle( $title, $check );
  }

  /**
    * @param string $pagename : titre de la page
    * @param int $namespace : constante de l'espace de nom de la page (ex.: 'NS_MAIN')
    *
    * @return string|null
    */
	public function getPageContentFromTitleText ( $pagename, $namespace ) {
    $page = self::getPageFromTitleText( $pagename, $namespace, true );
    if ( is_null( $page ) ) {
      return null;
    }
		return $page->getContent()->getNativeData();
	}

  /**
    * recherche l'existence d'une redirection
    * renvoie le texte du titre de la page redirigée
    *
    * @param WikiPage $page : titre de la page
    * @param string $output 'title'|'string'
    * @return Title|string|false
    */
	public function getPageRedirect ( $page, $output = 'string' ) {
		$return = [];
		$content = $page->getContent()->getNativeData();
    $screen = preg_match( '/^\#REDIRECTION \[\[(.*)\]\]/', $content, $matches );
    if ( $screen > 0 ) {
      switch ( $output ) {
        case 'title':
          return Title::newFromText( $matches[1] );
          break;

        default:
          return $matches[1];
          break;
      }
    }
		return null;
	}

  /**
    * recherche la valeur des paramètres d'un modèle inclus
    *
    * @param WikiPage $page : titre de la page
    * @param string $template : nom du modèle
    * @param array $fields : champs recherchés
    *
    * @return array( "field" => data, ... )|null
    */
	public function getPageTemplateInfos ( $page, $template, $fields ) {
		$return = [];
		$content = $page->getContent()->getNativeData();
		//$content = str_replace( '}}','',$content );
		$content = explode('{{', $content );
		foreach ( $content as $key => $string ) {
			$screen = preg_match( '/^' . $template . '[\s\|]/', $string);
			if ( $screen > 0 ) {
				$data = explode( '|', $string );
				foreach ( $fields as $kkey => $field ) {
					foreach ( $data as $kkkey => $dat ) {
						$screen = preg_match('/^'.$field.'[ ]*=(.+)[\s\|]/', $dat, $matches );
            if ( isset( $matches[1] ) ) {
							$return[$field] = $matches[1];
            }
					}
          if ( !isset($return[$field]) ) {
            $return[$field] = null;
          }
				}
			}
		}
    if ( sizeof( $return ) == 0 ) $return = null;
		return $return;
	}

  /**
    * écriture du contenu d'une page
    *
    * @param WikiPage $page
    * @param string $newtext
    * @param string $edit_summary
    * @param int $flags
    *
    * @return bool
    */
  public function writeContent ( $page, $newtext, $edit_summary, $flags = 0 ) {
    global $wgUser;
    $newcontent = new WikitextContent( $newtext );
    // cf: docs/pageupdater.txt
    $updater = $page->newPageUpdater( $wgUser );
    $updater->setContent( 'main', $newcontent ); // SlotRecord::MAIN = 'main'
    $updater->setRcPatrolStatus( 1 ); // RecentChange::PRC_PATROLLED = 1
    $comment = CommentStoreComment::newUnsavedComment( $edit_summary );
    $newRev = $updater->saveRevision( $comment, $flags );

    return ( !is_null( $newRev ) && $updater->wasSuccessful() );
  }

  /**
   * @param string $titletext
   * @param int $namespace
   * @param string $wikitextContent
   * @param string $summary
   * @param User $user
   *
   * @return MGWStatus
   */
  public static function newPage( $titletext, $namespace, $wikitext, $summary, $user ) {

    global $wgNamespaceAliases;

    $title = Title::newFromText( $titletext, $namespace );
    $article = WikiPage::factory( $title );
    $content = new WikitextContent( $wikitext );
    $flags = EDIT_NEW;
    $status = $article->doEditContent( $content, $summary, $flags, false, $user );
    if ( !$status->isOK() ) {
      return Status::newFailed( $status->getMessage() );
    }
    else {
      return Status::newDone( 'La page "' . array_search ( $namespace, $wgNamespaceAliases ) .
        ":" . $titletext . '" a été créée.', $article->getId() );
    }
  }

  /**
   * @param string $oldTitletext
   * @param int $oldNamespace
   * @param string $newTitletext
   * @param int $newNamespace
   * @param string $summary
   * @param User $user
   *
   * @return MGWStatus
   */
  public static function renamePage( $oldTitletext, $oldNamespace, $newTitleText, $newNamespace, $summary, $user ) {

    $oldTitle = Title::newFromText( $oldTitletext, $oldNamespace );
    $newTitle = Title::newFromText( $newTitleText, $newNamespace );
    $movePage = new MovePage( $oldTitle, $newTitle );
    if ( $movePage->isValidMove() ) {
      $movePage->move( $user, $summary, $createRedirect = true );
      return Status::newDone( 'La page a été renommée', $newTitle->getArticleID() );
    }
    else {
      return Status::newFailed( 'Impossible de renomer la page' );
    }
  }

  /**
   * @param int $page_id
   * @param string $summary
   * @param User $user
   *
   * @return MGWStatus
   */
  public static function refreshPage( $page_id, $summary, $user ) {

    $title = Title::newFromID( $page_id );
    if ( is_null( $title ) ) {
      return Status::newFailed( 'La page n\'existe pas.', MGW_PAGE_MISSING );
    }
    $article = WikiPage::factory( $title );
    $content = $article->getContent(); // enregistrement sans modification pour actualiser les parsers
    $flags = EDIT_MINOR;
    $ret = $article->doEditContent( $content, $summary, $flags, false, $user );

    if ( ! $ret->isOK() ) {
      return Status::newFailed( 'La page n\'a pas pu être rafraîchie ( ' .
        $ret->getMessage() .' )' );
    }
    else {
      return Status::newDone( 'La page a été rafraîchie' );
    }
  }

  /**
   * @param int $page_id
   * @param string $summary
   *
   * @return MGWStatus
   */
  public static function lightDelete( $page_id, $reason ) {

    $title = Title::newFromID( $page_id );
    if ( !is_null( $title ) ) {
      $article = WikiPage::factory( $title );
      $delete = $article->doDeleteArticleReal( $reason );
      if ( ! $delete->isOK() ) {
        return Status::newFailed( 'La page n\'a pas pu être supprimée ( ' .
          $delete->getMessage() .' )' );
      }
    }
    return Status::newDone( 'La page a été supprimée' );
  }


  /**
   * @param int $page_id
   * @param string $summary
   * @param User $user
   *
   * @return MGWStatus
   */
  public static function undeletePage( $page_name, $summary, $user ) {

    $title = Title::newFromText( $page_name );
    if ( !$title ) {
       return Status::newFailed( "Invalid title" );
    }

    $archive = new PageArchive( $title, \RequestContext::getMain()->getConfig() );
    $archive->undeleteAsUser( [], $user, $reason );
    $page_id = $title->getArticleID();

    if ( $page_id < 1 ) {
      return Status::newFailed( 'La page n\'a pas pu être restaurée' );
    }
    else {
      return Status::newDone( 'La page a été restaurée', $page_id );
    }
  }
}
