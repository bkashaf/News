<?php
/**
 * Zikula Application Framework
 *
 * @copyright  (c) Zikula Development Team
 * @link       http://www.zikula.org
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @author     Mark West <mark@zikula.org>
 * @category   Zikula_3rdParty_Modules
 * @package    Content_Management
 * @subpackage News
 */

class News_Controller_Ajax extends Zikula_Controller
{
    /**
     * modify a news entry (incl. delete) via ajax
     *
     * @author Frank Schummertz
     * @param 'sid'   int the story id
     * @param 'page'   int the story page
     * @return string HTML string
     */
    public function modify()
    {
        $sid  = FormUtil::getPassedValue('sid', null, 'POST');
        $page = FormUtil::getPassedValue('page', 1, 'POST');

        // Get the news article
        $item = ModUtil::apiFunc('News', 'user', 'get', array('sid' => $sid));
        if ($item == false) {
            AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__f('Error! No such article found.')));
        }

        // Security check
        if (!SecurityUtil::checkPermission('News::', "$item[cr_uid]::$sid", ACCESS_EDIT)) {
            AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__('Sorry! You do not have authorisation for this page.')));
        }

        // Get the format types. 'home' string is bits 0-1, 'body' is bits 2-3.
        $item['hometextcontenttype'] = ($item['format_type']%4);
        $item['bodytextcontenttype'] = (($item['format_type']/4)%4);

        // Set the publishing date options.
        if (!isset($item['to'])) {
            if (DateUtil::getDatetimeDiff_AsField($item['from'], $item['cr_date'], 6) >= 0 && is_null($item['to'])) {
                $item['unlimited'] = 1;
                $item['tonolimit'] = 1;
            } elseif (DateUtil::getDatetimeDiff_AsField($item['from'], $item['cr_date'], 6) < 0 && is_null($item['to'])) {
                $item['unlimited'] = 0;
                $item['tonolimit'] = 1;
            }
        } else {
            $item['unlimited'] = 0;
            $item['tonolimit'] = 0;
        }

        $this->setCaching(false);

        $modvars = ModUtil::getVar('News');
        $this->view->assign($modvars);

        if ($modvars['enablecategorization']) {
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'news');
            $this->view->assign('catregistry', $catregistry);
        }

        // Assign the item to the template
        $this->view->assign($item);

        // Assign the current page
        $this->view->assign('page', $page);

        // Assign the default languagecode
        $this->view->assign('lang', ZLanguage::getLanguageCode());

        // Assign the content format
        $formattedcontent = ModUtil::apiFunc('News', 'user', 'isformatted', array('func' => 'modify'));
        $this->view->assign('formattedcontent', $formattedcontent);

        // Return the output that has been generated by this function
        return array('result' => $this->view->fetch('ajax/modify.tpl'));
    }

    /**
     * This is the Ajax function that is called with the results of the
     * form supplied by news_ajax_modify() to update a current item
     * The following parameters are received in an array 'story'!
     *
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
     * @param int 'hideonindex' hide the article on the index page
     * @param string 'action' the action to perform, either 'update', 'delete' or 'pending'
     * @author Mark West
     * @author Frank Schummertz
     * @return array(output, action) with output being a rendered template or a simple text and action the performed action
     */
    public function update()
    {
        $story  = FormUtil::getPassedValue('story', null, 'POST');
        $action = FormUtil::getPassedValue('action', null, 'POST');
        $page   = (int)FormUtil::getPassedValue('page', 1, 'POST');

        // Get the current news article
        $item = ModUtil::apiFunc('News', 'user', 'get', array('sid' => $story['sid']));
        if ($item == false || !$action) {
            AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__('Error! No such article found.')));
        }

        if (!SecurityUtil::confirmAuthKey()) {
            AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__("Sorry! Invalid authorisation key ('authkey'). This is probably either because you pressed the 'Back' button to return to a page which does not allow that, or else because the page's authorisation key expired due to prolonged inactivity. Please refresh the page and try again.")));
        }

        $output = $action;
        $oldurltitle = $item['urltitle'];

        switch ($action)
        {
            case 'update':
            // Update the story, security check inside of the API func

                $modvars = ModUtil::getVar('News');
                /*            // delete and add images (credit msshams)
            if ($modvars['picupload_enabled']) {
                //  include the phpthumb library
                require_once ('pnincludes/phpthumb/ThumbLib.inc.php');
                $uploaddir = $modvars['picupload_uploaddir'] . '/';
                // remove selected files
                for ($i=0; $i<$item['pictures']; $i++){
                    if (isset($story['del_pictures-'.$i])) {
                        unlink($uploaddir.'pic_sid'.$story['sid']."-".$i."-norm.png");
                        unlink($uploaddir.'pic_sid'.$story['sid']."-".$i."-thumb.png");
                        unlink($uploaddir.'pic_sid'.$story['sid']."-".$i."-thumb2.png");
                        $story['pictures']--;
                    }
                }
                // renumber the remaining files if files were deleted
                if ($story['pictures'] != $item['pictures'] && $story['pictures'] != 0) {
                    $lastfile = 0;
                    for ($i=0; $i<$item['pictures']; $i++){
                        if (file_exists($uploaddir.'pic_sid'.$story['sid']."-".$i."-norm.png")) {
                            rename($uploaddir.'pic_sid'.$story['sid']."-".$i."-norm.png", $uploaddir.'pic_sid'.$story['sid']."-".$lastfile."-norm.png");
                            rename($uploaddir.'pic_sid'.$story['sid']."-".$i."-thumb.png", $uploaddir.'pic_sid'.$story['sid']."-".$lastfile."-thumb.png");
                            rename($uploaddir.'pic_sid'.$story['sid']."-".$i."-thumb2.png", $uploaddir.'pic_sid'.$story['sid']."-".$lastfile."-thumb2.png");
                            // create a new hometext image if needed
                            if ($lastfile == 0 && !file_exists($uploaddir.'pic_sid'.$story['sid']."-".$lastfile."-thumb2.png")){
                                $thumb2 = PhpThumbFactory::create($uploaddir.'pic_sid'.$story['sid']."-".$lastfile."-norm.png");
                                if ($modvars['sizing'] == 0) {
                                    $thumb2->Resize($modvars['picupload_thumb2maxwidth'],$modvars['picupload_thumb2maxheight']);
                                } else {
                                    $thumb2->adaptiveResize($modvars['picupload_thumb2maxwidth'],$modvars['picupload_thumb2maxheight']);
                                }
                                $thumb2->save($uploaddir.'pic_sid'.$story['sid'].'-'.$lastfile.'-thumb2.png', 'png');
                            }
                            $lastfile++;
                        }
                    }
                }
                // handling of additional image uploads
                foreach ($_FILES['news_files']['error'] as $key => $error) {
                    if ($error == UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['news_files']['tmp_name'][$key];
                        $name = $_FILES['news_files']['name'][$key];

                        $thumb = PhpThumbFactory::create($tmp_name);
                        if ($modvars['sizing'] == 0) {
                            $thumb->Resize($modvars['picupload_picmaxwidth'],$modvars['picupload_picmaxheight']);
                        } else {
                            $thumb->adaptiveResize($modvars['picupload_picmaxwidth'],$modvars['picupload_picmaxheight']);
                        }
                        $thumb->save($uploaddir.'pic_sid'.$story['sid'].'-'.$story['pictures'].'-norm.png', 'png');

                        $thumb1 = PhpThumbFactory::create($tmp_name);
                        if ($modvars['sizing'] == 0) {
                            $thumb1->Resize($modvars['picupload_thumbmaxwidth'],$modvars['picupload_thumbmaxheight']);
                        } else {
                            $thumb1->adaptiveResize($modvars['picupload_thumbmaxwidth'],$modvars['picupload_thumbmaxheight']);
                        }
                        $thumb1->save($uploaddir.'pic_sid'.$story['sid'].'-'.$story['pictures'].'-thumb.png', 'png');

                        // for index page picture create extra thumbnail
                        if ($story['pictures']==0){
                            $thumb2 = PhpThumbFactory::create($tmp_name);
                            if ($modvars['sizing'] == 0) {
                                $thumb2->Resize($modvars['picupload_thumb2maxwidth'],$modvars['picupload_thumb2maxheight']);
                            } else {
                                $thumb2->adaptiveResize($modvars['picupload_thumb2maxwidth'],$modvars['picupload_thumb2maxheight']);
                            }
                            $thumb2->save($uploaddir.'pic_sid'.$story['sid'].'-'.$story['pictures'].'-thumb2.png', 'png');
                        }
                        $story['pictures']++;
                    }
                }
            }*/

                if (ModUtil::apiFunc('News', 'admin', 'update',
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
                'hideonindex' => isset($story['hideonindex']) ? $story['hideonindex'] : 1,
                'disallowcomments' => isset($story['disallowcomments']) ? $story['disallowcomments'] : 0,
                'unlimited' => isset($story['unlimited']) ? $story['unlimited'] : null,
                'from' => isset($story['from']) ? $story['from'] : null,
                'tonolimit' => isset($story['tonolimit']) ? $story['tonolimit'] : null,
                'to' => isset($story['to']) ? $story['to'] : null,
                'weight' => $story['weight'],
                'pictures' => $story['pictures'],
                'published_status' => $story['published_status']))) {

                    // Success
                    // reload the news story and ignore the DBUtil SQLCache
                    $item = ModUtil::apiFunc('News', 'user', 'get', array('sid' => $story['sid'], 'SQLcache' => false));

                    if ($item == false) {
                        AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__('Error! No such article found.')));
                    }

                    // Explode the news article into an array of seperate pages
                    $allpages = explode('<!--pagebreak-->', $item['bodytext']);

                    // Set the item hometext to be the required page
                    // no arrays start from zero, pages from one
                    $item['bodytext'] = $allpages[$page-1];
                    $numitems = count($allpages);
                    unset($allpages);

                    // $info is array holding raw information.
                    $info = ModUtil::apiFunc('News', 'user', 'getArticleInfo', $item);

                    // $links is an array holding pure URLs to
                    // specific functions for this article.
                    $links = ModUtil::apiFunc('News', 'user', 'getArticleLinks', $info);

                    // $preformat is an array holding chunks of
                    // preformatted text for this article.
                    $preformat = ModUtil::apiFunc('News', 'user', 'getArticlePreformat',
                            array('info'  => $info,
                            'links' => $links));

                    $this->setCaching(false);

                    // Assign the story info arrays
                    $this->view->assign(array('info'      => $info,
                            'links'     => $links,
                            'preformat' => $preformat,
                            'page'      => $page));
                    // Some vars
                    $this->view->assign('enablecategorization', $modvars['enablecategorization']);
                    $this->view->assign('catimagepath', $modvars['catimagepath']);
                    $this->view->assign('enableajaxedit', $modvars['enableajaxedit']);

                    // Now lets assign the information to create a pager for the review
                    $this->view->assign('pager', array('numitems' => $numitems,
                            'itemsperpage' => 1));

                    // we do not increment the read count!!!

                    // when urltitle has changed, do a reload with the full url and switch to no shorturl usage
                    if (strcmp($oldurltitle, $item['urltitle']) != 0) {
                        $reloadurl = ModUtil::url('News', 'user', 'display', array('sid' => $info['sid'], 'page' => $page), null, null, true, true);
                    } else {
                        $reloadurl = '';
                    }

                    // Return the output that has been generated by this function
                    $output = $this->view->fetch('user/articlecontent.tpl');
                } else {
                    $output = DataUtil::formatForDisplayHTML($this->__('Error! Could not save your changes.'));
                }
                break;

            case 'pending':
            // Security check
                if (!SecurityUtil::checkPermission('News::', "$item[cr_uid]::$story[sid]", ACCESS_EDIT)) {
                    AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__('Sorry! You do not have authorisation for this page.')));
                }
                // set published_status to 2 to make the story a pending story
                $object = array('published_status' => 2,
                        'sid'              => $story['sid']);

                if (DBUtil::updateObject($object, 'news', '', 'sid') == false) {
                    $output = DataUtil::formatForDisplayHTML($this->__('Error! Could not save your changes.'));
                } else {
                    // Success
                    // the url for reloading, after setting to pending refer to the news index since this article is not visible any more
                    $reloadurl = ModUtil::url('News', 'user', 'view', array(), null, null, true);
                    $output = DataUtil::formatForDisplayHTML($this->__f('Done! Saved your changes.'));
                }
                break;

            case 'delete':
            // Security check inside of the API func
                if (ModUtil::apiFunc('News', 'admin', 'delete', array('sid' => $story['sid']))) {
                    // Success
                    // the url for reloading, after deleting refer to the news index
                    $reloadurl = ModUtil::url('News', 'user', 'view', array(), null, null, true);
                    $output = DataUtil::formatForDisplayHTML($this->__f('Done! Deleted article.'));
                } else {
                    $output = DataUtil::formatForDisplayHTML($this->__('Error! Could not delete article.'));
                }
                break;

            default:
        }

        return array('result' => $output,
                'action' => $action,
                'reloadurl' => $reloadurl);
    }


    /**
     * This is the Ajax function that is called with the results of the
     * form supplied by news_<user/admin>_new() to create a new draft item
     * The following parameters are received in an array 'story'!
     *
     * @param string 'title' the title of the news item
     *
     * @author Erik Spaan
     * @return array(output, etc) with output being a rendered template or a simple text and action the performed action
     */
    public function savedraft()
    {
        $title = FormUtil::getPassedValue('title', null, 'POST');
        $sid   = FormUtil::getPassedValue('sid', null, 'POST');
        $story = FormUtil::getPassedValue('story', null, 'POST');

        $output = $title;
        $slug = '';
        $fullpermalink = '';
        $showslugedit = false;
        // Permalink display length, only needed for 2 column layout later.
        //$permalinkmaxdisplay = 40;

        // Check  if the article is already saved as draft
        if ($sid > 0) {
            // Get the current news article
            $item = ModUtil::apiFunc('News', 'user', 'get', array('sid' => $sid));
            if ($item == false) {
                AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__f('Error! No such article found.')));
            }
            // Security check
            if (!SecurityUtil::checkPermission('News::', "$item[cr_uid]::$sid", ACCESS_EDIT)) {
                AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__('Sorry! You do not have authorisation for this page.')));
            }

            if (!ModUtil::apiFunc('News', 'admin', 'update',
            array('sid' => $sid,
            'title' => DataUtil::convertFromUTF8($story['title']),
            'urltitle' => DataUtil::convertFromUTF8($story['urltitle']),
            '__CATEGORIES__' => $story['__CATEGORIES__'],
            'language' => isset($story['language']) ? $story['language'] : '',
            'hometext' => DataUtil::convertFromUTF8($story['hometext']),
            'hometextcontenttype' => $story['hometextcontenttype'],
            'bodytext' => DataUtil::convertFromUTF8($story['bodytext']),
            'bodytextcontenttype' => $story['bodytextcontenttype'],
            'notes' => DataUtil::convertFromUTF8($story['notes']),
            'hideonindex' => isset($story['hideonindex']) ? $story['hideonindex'] : 1,
            'disallowcomments' => isset($story['disallowcomments']) ? $story['disallowcomments'] : 0,
            'unlimited' => isset($story['unlimited']) ? $story['unlimited'] : null,
            'from' => $story['from'],
            'tonolimit' => isset($story['tonolimit']) ? $story['tonolimit'] : null,
            'to' => $story['to'],
            'weight' => $story['weight'],
            'pictures' => $story['pictures'] ))) {

                $output = DataUtil::formatForDisplayHTML($this->__('Error! Could not save your changes.'));
            } else {
                $output = $this->__f('Draft updated at %s', DateUtil::getDatetime_Time('', '%H:%M'));
                // Return the permalink (domain shortened) and the slug of the permalink
                $slug = $item['urltitle'];
                $fullpermalink = DataUtil::formatForDisplayHTML(ModUtil::url('News', 'user', 'display', array('sid' => $sid)));
                // limit the display length of the permalink
                //if (strlen($fullpermalink) > $permalinkmaxdisplay) {
                //    $fullpermalink = '...' . substr($fullpermalink, strlen($fullpermalink) - $permalinkmaxdisplay, $permalinkmaxdisplay);
                //}
                // Only show "edit the slug" if the shorturls are active
                $showslugedit = (System::getVar('shorturls') && System::getVar('shorturlstype') == 0);
            }
        } else {
            // Create a first draft version of the story
            if ($sid = ModUtil::apiFunc('News', 'user', 'create',
            array('title' => DataUtil::convertFromUTF8($title),
            '__CATEGORIES__' => isset($story['__CATEGORIES__']) ? $story['__CATEGORIES__'] : null,
            'language' => isset($story['language']) ? $story['language'] : '',
            'hometext' => isset($story['hometext']) ? DataUtil::convertFromUTF8($story['hometext']) : '',
            'hometextcontenttype' => isset($story['hometextcontenttype']) ? $story['hometextcontenttype'] : 0,
            'bodytext' => isset($story['bodytext']) ? DataUtil::convertFromUTF8($story['bodytext']) : '',
            'bodytextcontenttype' => isset($story['bodytextcontenttype']) ? $story['bodytextcontenttype'] : 0,
            'notes' => isset($story['notes']) ? DataUtil::convertFromUTF8($story['notes']) : '',
            'hideonindex' => isset($story['hideonindex']) ? $story['hideonindex'] : 1,
            'disallowcomments' => isset($story['disallowcomments']) ? $story['disallowcomments'] : 0,
            'unlimited' => isset($story['unlimited']) ? $story['unlimited'] : null,
            'from' => isset($story['from']) ? $story['from'] : null,
            'tonolimit' => isset($story['tonolimit']) ? $story['tonolimit'] : null,
            'to' => isset($story['to']) ? $story['to'] : null,
            'weight' => isset($story['weight']) ? $story['weight'] : 0,
            'pictures' => isset($story['pictures']) ? $story['pictures'] : 0,
            'published_status' => 4 ))) {
                // Success and now reload the news story
                $item = ModUtil::apiFunc('News', 'user', 'get', array('sid' => $sid));
                if ($item == false) {
                    AjaxUtil::error(DataUtil::formatForDisplayHTML($this->__('Error! No such article found.')));
                } else {
                    // Return the Draft creation date
                    $output = $this->__f('Draft saved at %s', DateUtil::getDatetime_Time($item['cr_date'], '%H:%M'));
                    // Return the permalink (domain shortened) and the slug of the permalink
                    $slug = $item['urltitle'];
                    $fullpermalink = DataUtil::formatForDisplayHTML(ModUtil::url('News', 'user', 'display', array('sid' => $sid)));
                    // limit the display length of the permalink
                    //if (strlen($fullpermalink) > $permalinkmaxdisplay) {
                    //    $fullpermalink = '...' . substr($fullpermalink, strlen($fullpermalink) - $permalinkmaxdisplay, $permalinkmaxdisplay);
                    //}
                    // Only show "edit the slug" if the shorturls are active
                    $showslugedit = (System::getVar('shorturls') && System::getVar('shorturlstype') == 0);
                }
            } else {
                $output = DataUtil::formatForDisplayHTML($this->__('Error! Could not save your changes.'));
            }
        }
        return array('result' => $output,
                'sid' => $sid,
                'slug' => $slug,
                'fullpermalink' => $fullpermalink,
                'showslugedit' => $showslugedit);

    }


    /**
     * make the permalink from the title
     *
     * @author Erik Spaan
     * @param 'title'   int the story id
     * @return string HTML string
     */
    public function updatepermalink()
    {
        $title = FormUtil::getPassedValue('title', '');

        // define the lowercase permalink, using the title as slug, if not present
//    if (!isset($args['urltitle']) || empty($args['urltitle'])) {
//        $args['urltitle'] = strtolower(DataUtil::formatPermalink($args['title']));
//    }

        // Construct the lowercase permalink, using the title as slug
        $permalink = strtolower(DataUtil::formatPermalink($title));

        return array('result' => $permalink);
    }
}