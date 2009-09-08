<?php
/**
 * Zikula Application Framework
 *
 * @copyright  (c) Zikula Development Team
 * @link       http://www.zikula.org
 * @version    $Id: pnajax.php 75 2009-02-24 04:51:52Z mateo $
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @author     Mark West <mark@zikula.org>
 * @category   Zikula_3rdParty_Modules
 * @package    Content_Management
 * @subpackage News
 */

/**
 * modify a news entry (incl. delete) via ajax
 *
 * @author Frank Schummertz
 * @param 'sid'   int the story id
 * @param 'page'   int the story page
 * @return string HTML string
 */
function News_ajax_modify()
{
    $dom = ZLanguage::getModuleDomain('News');
    $sid  = FormUtil::getPassedValue('sid', null, 'POST');
    $page = FormUtil::getPassedValue('page', 1, 'POST');

    // Get the news article
    $item = pnModAPIFunc('News', 'user', 'get', array('sid' => $sid));
    if ($item == false) {
        AjaxUtil::error(DataUtil::formatForDisplayHTML(__('No such news article found.', $dom)));
    }

    // Security check
    if (!(SecurityUtil::checkPermission('News::', "$item[aid]::$sid", ACCESS_EDIT) ||
          SecurityUtil::checkPermission('Stories::Story', "$item[aid]::$sid", ACCESS_EDIT))) {
        AjaxUtil::error(DataUtil::formatForDisplayHTML(__('Sorry! No authorization to access this module.', $dom)));
    }

    // load language file
    pnModLangLoad('News', 'admin');

    // Get the format types. 'home' string is bits 0-1, 'body' is bits 2-3.
    $item['hometextcontenttype'] = ($item['format_type']%4);
    $item['bodytextcontenttype'] = (($item['format_type']/4)%4);

    // Set the publishing date options.
    if (!isset($item['to'])) {
    	if (DateUtil::getDatetimeDiff_AsField($item['from'], $item['time'], 6) >= 0) {
    		$item['unlimited'] = 1;
            $item['tonolimit'] = 0;
        } elseif (DateUtil::getDatetimeDiff_AsField($item['from'], $item['time'], 6) < 0) {
            $item['unlimited'] = 0;
            $item['tonolimit'] = 1;
        }
    } else {
        $item['unlimited'] = 0;
        $item['tonolimit'] = 0;
    }

    // Create output object
    $renderer = pnRender::getInstance('News', false);

    $modvars = pnModGetVar('News');
    $renderer->assign($modvars);

    if ($modvars['enablecategorization']) {
        // load the categories system
        if (!($class = Loader::loadClass('CategoryRegistryUtil'))) {
            pn_exit (__('Error! Unable to load class CategoryRegistryUtil', $dom));
        }
        $categories  = CategoryRegistryUtil::getRegisteredModuleCategories ('News', 'news');

        $renderer->assign('categories', $categories);
    }

    // Assign the item to the template
    $renderer->assign($item);

    // Assign the current page
    $renderer->assign('page', $page);

    // Assign the content format
    $formattedcontent = pnModAPIFunc('News', 'user', 'isformatted', array('func' => 'modify'));
    $renderer->assign('formattedcontent', $formattedcontent);

    // Return the output that has been generated by this function
    return array('result' => $renderer->fetch('news_ajax_modify.htm'));
}

/**
 * This is the Ajax function that is called with the results of the
 * form supplied by news_ajax_modify() to update a current item
 * The following parameters are received in an array 'story'!
 * @param int 'sid' the id of the item to be updated
 * @param string 'title' the title of the news item
 * @param string 'urltitle' the title of the news item formatted for the url
 * @param string 'language' the language of the news item
 * @param string 'bodytext' the summary text of the news item
 * @param int 'bodytextcontenttype' the content type of the summary text
 * @param string 'extendedtext' the body text of the news item
 * @param int 'extendedtextcontenttype' the content type of the body text
 * @param string 'notes' any administrator notes
 * @param int 'published_status' the published status of the item
 * @param int 'ihome' publish the article in the homepage
 * @param string 'action' the action to perform, either 'update', 'delete' or 'pending'
 * @author Mark West
 * @author Frank Schummertz
 * @return array(output, action) with output being a rendered template or a simple text and action the performed action
 */
function News_ajax_update()
{
    $story = FormUtil::getPassedValue('story', null, 'POST');
    $page  = (int)FormUtil::getPassedValue('page', 1, 'POST');

    // Get the current news article
    $item = pnModAPIFunc('News', 'user', 'get', array('sid' => $story['sid']));
    if ($item == false) {
        AjaxUtil::error(DataUtil::formatForDisplayHTML(__('No such news article found.', $dom)));
    }

    if (!SecurityUtil::confirmAuthKey()) {
        AjaxUtil::error(DataUtil::formatForDisplayHTML(__('Invalid \'authkey\':  this probably means that you pressed the \'Back\' button, or that the page \'authkey\' expired. Please refresh the page and try again.', $dom)));
    }

    $oldurltitle = $item['urltitle'];

    // Notable by its absence there is no security check here

    pnModLangLoad('News', 'admin');

    $output = $story['action'];
    switch($story['action']) {
        case 'update':
            // Security check
            if (!(SecurityUtil::checkPermission('News::', "$item[aid]::$story[sid]", ACCESS_EDIT) ||
                  SecurityUtil::checkPermission('Stories::Story', "$item[aid]::$story[sid]", ACCESS_EDIT))) {
                AjaxUtil::error(DataUtil::formatForDisplayHTML(__('Sorry! No authorization to access this module.', $dom)));
            }
            // Update the story
            if (pnModAPIFunc('News', 'admin', 'update',
                            array('sid' => $story['sid'],
                                  'title' => DataUtil::convertFromUTF8($story['title']),
                                  'urltitle' => DataUtil::convertFromUTF8($story['urltitle']),
                                  '__CATEGORIES__' => $story['__CATEGORIES__'],
                                  'language' => isset($story['language']) ? $story['language'] : '',
                                  'hometext' => DataUtil::convertFromUTF8($story['hometext']),
                                  'hometextcontenttype' => $story['hometextcontenttype'],
                                  'bodytext' => DataUtil::convertFromUTF8($story['bodytext']),
                                  'bodytextcontenttype' => $story['bodytextcontenttype'],
                                  'notes' => DataUtil::convertFromUTF8($story['notes']),
                                  'ihome' => isset($story['ihome']) ? $story['ihome'] : 1,
                                  'withcomm' => isset($story['withcomm']) ? $story['withcomm'] : 0,
                                  'unlimited' => isset($story['unlimited']) ? $story['unlimited'] : null,
                                  'from' => mktime($story['fromHour'], $story['fromMinute'], 0, $story['fromMonth'], $story['fromDay'], $story['fromYear']),
                                  'tonolimit' => isset($story['tonolimit']) ? $story['tonolimit'] : null,
                                  'to' => mktime($story['toHour'], $story['toMinute'], 0, $story['toMonth'], $story['toDay'], $story['toYear']),
                                  'published_status' => $story['published_status']))) {

                // Success
                // reload the news story and ignore the DBUtil SQLCache
                $item = pnModAPIFunc('News', 'user', 'get', array('sid' => $story['sid'], 'SQLcache' => false));

                if ($item == false) {
                    AjaxUtil::error(DataUtil::formatForDisplayHTML(__('No such item found.', $dom)));
                }

                // Explode the news article into an array of seperate pages
                $allpages = explode('<!--pagebreak-->', $item['bodytext']);

                // Set the item hometext to be the required page
                // no arrays start from zero, pages from one
                $item['bodytext'] = $allpages[$page-1];
                $numitems = count($allpages);
                unset($allpages);

                // $info is array holding raw information.
                $info = pnModAPIFunc('News', 'user', 'getArticleInfo', $item);

                // $links is an array holding pure URLs to
                // specific functions for this article.
                $links = pnModAPIFunc('News', 'user', 'getArticleLinks', $info);

                // $preformat is an array holding chunks of
                // preformatted text for this article.
                $preformat = pnModAPIFunc('News', 'user', 'getArticlePreformat',
                                          array('info'  => $info,
                                                'links' => $links));

                // Create output object
                $renderer = pnRender::getInstance('News', false);

                // Assign the story info arrays
                $renderer->assign(array('info'      => $info,
                                        'links'     => $links,
                                        'preformat' => $preformat,
                                        'page'      => $page));
                // Some vars
                $renderer->assign('enablecategorization', pnModGetVar('News', 'enablecategorization'));
                $renderer->assign('catimagepath', pnModGetVar('News', 'catimagepath'));
                $renderer->assign('enableajaxedit', pnModGetVar('News', 'enableajaxedit'));

                // Now lets assign the information to create a pager for the review
                $renderer->assign('pager', array('numitems' => $numitems,
                                                 'itemsperpage' => 1));

                // we do not increment the read count!!!

                // load language file
                pnModLangLoad('News', 'user');

                // when urltitle has changed, do a reload with the full url and switch to no shorturl usage
                if (strcmp($oldurltitle, $item['urltitle']) != 0) {
                    $reloadurl = pnModURL('News', 'user', 'display', array('sid' => $info['sid'], 'page' => $page), null, null, true, true);
                } else {
                    $reloadurl = '';
                }

                // Return the output that has been generated by this function
                $output = $renderer->fetch('news_user_articlecontent.htm');
            } else {
                $output = DataUtil::formatForDisplayHTML(__('Error! Update attempt failed.', $dom));
            }
            break;

        case 'pending':
            // Security check
            if (!(SecurityUtil::checkPermission('News::', "$item[aid]::$story[sid]", ACCESS_EDIT) ||
                  SecurityUtil::checkPermission('Stories::Story', "$item[aid]::$story[sid]", ACCESS_EDIT))) {
                AjaxUtil::error(DataUtil::formatForDisplayHTML(__('Sorry! No authorization to access this module.', $dom)));
            }
            // set published_status to 2 to make the story a pending story
            $object = array('published_status' => 2,
                            'sid'              => $story['sid']);
            if (DBUtil::updateObject($object, 'news', '', 'sid') == false) {
                $output = DataUtil::formatForDisplayHTML(__('Error! Update attempt failed.', $dom));
            } else {
                // Success
                // the url for reloading, after setting to pending refer to the news index since this article is not visible any more
                $reloadurl = pnModURL('News', 'user', 'view', array(), null, null, true);
                $output = DataUtil::formatForDisplayHTML(__('Done! News article updated.', $dom));
            }
            break;

        case 'delete':
            // Security check
            if (!(SecurityUtil::checkPermission('News::', "$item[aid]::$story[sid]", ACCESS_DELETE) ||
                  SecurityUtil::checkPermission('Stories::Story', "$item[aid]::$story[sid]", ACCESS_DELETE))) {
                AjaxUtil::error(DataUtil::formatForDisplayHTML(__('Sorry! No authorization to access this module.', $dom)));
            }
            if (pnModAPIFunc('News', 'admin', 'delete', array('sid' => $story['sid']))) {
                // Success
                // the url for reloading, after deleting refer to the news index
                $reloadurl = pnModURL('News', 'user', 'view', array(), null, null, true);
                $output = DataUtil::formatForDisplayHTML(__('Done! News article deleted.', $dom));
            } else {
                $output = DataUtil::formatForDisplayHTML(__('Error! Sorry! Deletion attempt failed.', $dom));
            }
            break;
        default:
    }
    return array('result' => $output,
                 'action' => $story['action'],
                 'reloadurl' => $reloadurl);
}