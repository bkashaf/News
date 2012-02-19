<?php
/**
 * Zikula Application Framework
 *
 * @copyright  (c) Zikula Development Team
 * @link       http://www.zikula.org
 * @version    $Id: pnuser.php 79 2009-02-25 17:41:17Z espaan $
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @author     Mark West <mark@zikula.org>
 * @category   Zikula_3rdParty_Modules
 * @package    Content_Management
 * @subpackage News
 */

/**
 * the main user function
 *
 * @author Mark West
 * @return string HTML string
 */
function News_user_main()
{
    $args = array(
        'hideonindex' => 0,
        'itemsperpage' => pnModGetVar('News', 'storyhome', 10)
    );
    return News_user_view($args);
}

/**
 * add new item
 *
 * @author Mark West
 * @return string HTML string
 */
function News_user_new($args)
{
    // Security check
    if (!SecurityUtil::checkPermission('News::', '::', ACCESS_COMMENT)) {
        return LogUtil::registerPermissionError();
    }

    $dom = ZLanguage::getModuleDomain('News');

    // Any item set for preview will be stored in a session var
    // Once the new article is posted we'll clear the session var.
    $item = SessionUtil::getVar('newsitem');

    // Admin functions of this type can be called by other modules.
    extract($args);

    // get the type parameter so we can decide what template to use
    $type = FormUtil::getPassedValue('type', 'user', 'REQUEST');

    // Set the default values for the form. If not previewing an item prior
    // to submission these values will be null but do need to be set
    if (empty($item)) {
        $item = array();
        $item['__CATEGORIES__'] = array();
        $item['__ATTRIBUTES__'] = array();
        $item['title'] = '';
        $item['urltitle'] = '';
        $item['hometext'] = '';
        $item['hometextcontenttype'] = '';
        $item['bodytext'] = '';
        $item['bodytextcontenttype'] = '';
        $item['notes'] = '';
        $item['hideonindex'] = 1;
        $item['language'] = '';
        $item['disallowcomments'] = 1;
        $item['from'] = DateUtil::getDatetime(null, '%Y-%m-%d %H:%M');
        $item['to'] = DateUtil::getDatetime(null, '%Y-%m-%d %H:%M');
        $item['tonolimit'] = 1;
        $item['unlimited'] = 1;
        $item['weight'] = 0;
        $item['pictures'] = 0;
    }
    
    $preview = '';
    if (isset($item['action']) && $item['action'] == 0) {
        $preview = News_user_preview(array('title' => $item['title'],
                                           'hometext' => $item['hometext'],
                                           'hometextcontenttype' => $item['hometextcontenttype'],
                                           'bodytext' => $item['bodytext'],
                                           'bodytextcontenttype' => $item['bodytextcontenttype'],
                                           'notes' => $item['notes']));
    }

    // Create output object
    if (strtolower($type) == 'admin') {
        $render = & pnRender::getInstance('News', false);
    } else {
        $render = & pnRender::getInstance('News');
    }

    // Get the module vars
    $modvars = pnModGetVar('News');

    if ($modvars['enablecategorization']) {
        // load the categories system
        if (!Loader::loadClass('CategoryRegistryUtil')) {
            return LogUtil::registerError(__f('Error! Could not load [%s] class.', 'CategoryRegistryUtil', $dom));
        }
        $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'news');
        $render->assign('catregistry', $catregistry);
        
        // add article attribute if morearticles is enabled and general setting is zero 
        if ($modvars['enablemorearticlesincat'] && $modvars['morearticlesincat'] == 0) {
            $item['__ATTRIBUTES__']['morearticlesincat'] = 0;
        }
    }

    $render->assign($modvars);

    // Assign the default languagecode
    $render->assign('lang', ZLanguage::getLanguageCode());
    
    // Assign the item to the template
    $render->assign($item);

    // Assign the content format
    $formattedcontent = pnModAPIFunc('News', 'user', 'isformatted', array('func' => 'new'));
    $render->assign('formattedcontent', $formattedcontent);

    $render->assign('accessadd', 0);
    if (SecurityUtil::checkPermission('News::', '::', ACCESS_ADD)) {
        $render->assign('accessadd', 1);
        $render->assign('accesspicupload', 1);
        $render->assign('accesspubdetails', 1);
    } else {
        // if higher level access_add is not permitted, check for more specific permission rights
        if (SecurityUtil::checkPermission('News:pictureupload:', '::', ACCESS_ADD)) {
            $render->assign('accesspicupload', 1);
        } else {
            $render->assign('accesspicupload', 0);
        }
        if (SecurityUtil::checkPermission('News:publicationdetails:', '::', ACCESS_ADD)) {
            $render->assign('accesspubdetails', 1);
        } else {
            $render->assign('accesspubdetails', 0);
        }
    }

    $render->assign('preview', $preview);

    // Return the output that has been generated by this function
    if (strtolower($type) == 'admin') {
        return $render->fetch('news_admin_new.htm');
    } else {
        return $render->fetch('news_user_new.htm');
    }
}

/**
 * This is a standard function that is called with the results of the
 * form supplied by News_admin_new() to create a new item
 *
 * @author Mark West
 * @param string 'title' the title of the news item
 * @param string 'language' the language of the news item
 * @param string 'hometext' the summary text of the news item
 * @param int 'hometextcontenttype' the content type of the summary text
 * @param string 'bodytext' the body text of the news item
 * @param int 'bodytextcontenttype' the content type of the body text
 * @param string 'notes' any administrator notes
 * @param int 'published_status' the published status of the item
 * @param int 'hideonindex' hide the article on the index page
 * @return bool true
 */
function News_user_create($args)
{
    $dom = ZLanguage::getModuleDomain('News');

    // Get parameters from whatever input we need
    $story = FormUtil::getPassedValue('story', isset($args['story']) ? $args['story'] : null, 'POST');

    // Create the item array for processing
    $item = array('title' => $story['title'],
                  'urltitle' => isset($story['urltitle']) ? $story['urltitle'] : '',
                  '__CATEGORIES__' => isset($story['__CATEGORIES__']) ? $story['__CATEGORIES__'] : null,
                  '__ATTRIBUTES__' => isset($story['attributes']) ? $story['attributes'] : null,
                  'language' => isset($story['language']) ? $story['language'] : '',
                  'hometext' => isset($story['hometext']) ? $story['hometext'] : '',
                  'hometextcontenttype' => $story['hometextcontenttype'],
                  'bodytext' => isset($story['bodytext']) ? $story['bodytext'] : '',
                  'bodytextcontenttype' => $story['bodytextcontenttype'],
                  'notes' => $story['notes'],
                  'hideonindex' => isset($story['hideonindex']) ? $story['hideonindex'] : 0,
                  'disallowcomments' => isset($story['disallowcomments']) ? $story['disallowcomments'] : 0,
                  'from' => isset($story['from']) ? $story['from'] : null,
                  'tonolimit' => isset($story['tonolimit']) ? $story['tonolimit'] : null,
                  'to' => isset($story['to']) ? $story['to'] : null,
                  'unlimited' => isset($story['unlimited']) && $story['unlimited'] ? true : false,
                  'weight' => isset($story['weight']) ? $story['weight'] : 0,
                  'action' => isset($story['action']) ? $story['action'] : 0);

    // Disable the non accessible fields for non editors
    if (!SecurityUtil::checkPermission('News::', '::', ACCESS_ADD)) {
        $item['notes'] = '';
        $item['hideonindex'] = 1;
        $item['disallowcomments'] = 1;
        $item['from'] = null;
        $item['tonolimit'] = true;
        $item['to'] = null;
        $item['unlimited'] = true;
        $item['weight'] = 0;
        if ($item['action'] > 1) {
            $item['action'] = 0;
        }
    }

    // Get the referer type for later use
    if (stristr(pnServerGetVar('HTTP_REFERER'), 'type=admin')) {
        $referertype = 'admin';
    } else {
        $referertype = 'user';
    }

    // Reformat the attributes array
    // from {0 => {name => '...', value => '...'}} to {name => value}
    if (isset($item['__ATTRIBUTES__'])) {
        $attributes = array();
        foreach ($item['__ATTRIBUTES__'] as $attr) {
            if (!empty($attr['name']) && !empty($attr['value'])) {
                $attributes[$attr['name']] = $attr['value'];
            }
        }
        $item['__ATTRIBUTES__'] = $attributes;
    }

    // Validate the input
    $validationerror = false;
    if ($item['action'] != 0 && empty($item['title'])) {
        $validationerror = __f('Error! You did not enter a %s.', __('title', $dom), $dom);
    }
    // both text fields can't be empty
    if ($item['action'] != 0 && empty($item['hometext']) && empty($item['bodytext'])) {
        $validationerror = __f('Error! You did not enter the minimum necessary %s.', __('article content', $dom), $dom);
    }

    // if the user has selected to preview the article we then route them back
    // to the new function with the arguments passed here
    if ($item['action'] == 0 || $validationerror !== false) {
        // log the error found if any
        if ($validationerror !== false) {
            LogUtil::registerError($validationerror);
        }
        // back to the referer form
        SessionUtil::setVar('newsitem', $item);
        return pnRedirect(pnModURL('News', $referertype, 'new'));

    } else {
        // Confirm authorisation code.
        if (!SecurityUtil::confirmAuthKey()) {
            return LogUtil::registerAuthidError(pnModURL('News', $referertype, 'view'));
        }

        // As we're not previewing the item let's remove it from the session
        SessionUtil::delVar('newsitem');
    }

    // get all module vars
    $modvars = pnModGetVar('News');
    
    // count the attached pictures (credit msshams)
    if ($modvars['picupload_enabled']) {
        $pics2resize = array();
        $picsuploaded = 0;
        $allowedExtensionsArray = explode(',', $modvars['picupload_allowext']);
        foreach ($_FILES['news_files']['error'] as $key => $error) {
            if ($error == UPLOAD_ERR_OK) {
                if ($_FILES['news_files']['size'][$key] <= $modvars['picupload_maxfilesize']) {
                    $file_extension = FileUtil::getExtension($_FILES['news_files']['name'][$key]);
                    if (!in_array(strtolower($file_extension), $allowedExtensionsArray) && !in_array(strtoupper(($file_extension)), $allowedExtensionsArray)) {
                        LogUtil::registerStatus(__f('Warning! Picture %s is not uploaded, since the file extension is now allowed (only %s is allowed).', array($key+1, $modvars['picupload_allowext']), $dom));
                    } else {
                        $pics2resize[] = $key;
                    }
                } else {
                    LogUtil::registerStatus(__f('Warning! Picture %s is not uploaded, since the filesize was too large (max. %s kB).', array($key+1, $modvars['picupload_maxfilesize']/1000), $dom));
                }
                $picsuploaded++;
            } elseif ($error == UPLOAD_ERR_FORM_SIZE) {
                LogUtil::registerStatus(__f('Warning! Picture %s is not uploaded, since the filesize was too large (max. %s kB).', array($key+1, $modvars['picupload_maxfilesize']/1000), $dom));
                $picsuploaded++;
            } elseif ($error != UPLOAD_ERR_NO_FILE) {
                LogUtil::registerStatus(__f('Warning! Picture %1$s gave an error (code %2$s, explained on this page: %3$s) during uploading.', array($key+1, $error, 'http://php.net/manual/features.file-upload.errors.php'), $dom));
                $picsuploaded++;
            }
        }
        $item['pictures'] = count($pics2resize);
        // make the article draft when there is an upload error and ADD permission is present
        if ($picsuploaded != count($pics2resize) && SecurityUtil::checkPermission('News::', '::', ACCESS_ADD)) {
            $item['action'] = 6;
        }
    } else {
        $item['pictures'] = 0;
    }

    // Notable by its absence there is no security check here
    
    // Create the news story
    $sid = pnModAPIFunc('News', 'user', 'create', $item);

    if ($sid != false) {
        // Success
        LogUtil::registerStatus(__('Done! Created new article.', $dom));

        // notify the configured addresses of a new Pending Review article
        $notifyonpending = pnModGetVar('News', 'notifyonpending', false);
        if ($notifyonpending && ($item['action'] == 1 || $item['action'] == 4)) {
            $sitename = pnConfigGetVar('sitename');
            $adminmail = pnConfigGetVar('adminmail');
            $fromname    = !empty($modvars['notifyonpending_fromname']) ? $modvars['notifyonpending_fromname'] : $sitename;
            $fromaddress = !empty($modvars['notifyonpending_fromaddress']) ? $modvars['notifyonpending_fromaddress'] : $adminmail;
            $toname    = !empty($modvars['notifyonpending_toname']) ? $modvars['notifyonpending_toname'] : $sitename;
            $toaddress = !empty($modvars['notifyonpending_toaddress']) ? $modvars['notifyonpending_toaddress'] : $adminmail;
            $subject     = $modvars['notifyonpending_subject'];
            $html        = $modvars['notifyonpending_html'];
            if (!pnUserLoggedIn()) {
                $contributor = pnConfigGetVar('anonymous');
            } else {
                $contributor = pnUserGetVar('uname');
            }
            if ($html) {
                $body = __f('<br />A News Publisher article <strong>%1$s</strong> has been submitted by %2$s for review on website %3$s.<br />Index page teaser text of the article:<br /><hr />%4$s<hr /><br /><br />Go to the <a href="%5$s">news publisher admin</a> pages to review and publish the <em>Pending Review</em> article(s).<br /><br />Regards,<br />%6$s', array($item['title'], $contributor, $sitename, $item['hometext'], pnModURL('News', 'admin', 'view', array('news_status' => 2), null, null, true), $sitename), $dom);
            } else {
                $body = __f('
A News Publisher article \'%1$s\' has been submitted by %2$s for review on website %3$s.
Index page teaser text of the article:
--------
%4$s
--------

Go to the <a href="%5$s">news publisher admin</a> pages to review and publish the \'Pending Review\' article(s).

Regards,
%6$s', array($item['title'], $contributor, $sitename, $item['hometext'], pnModURL('News', 'admin', 'view', array('news_status' => 2), null, null, true), $sitename), $dom);
            }
            $sent = pnModAPIFunc('Mailer', 'user', 'sendmessage', array('toname'     => $toname,
                                                                        'toaddress'  => $toaddress,
                                                                        'fromname'   => $fromname,
                                                                        'fromaddress'=> $fromaddress,
                                                                        'subject'    => $subject,
                                                                        'body'       => $body,
                                                                        'html'       => $html));
            if ($sent) {
                LogUtil::registerStatus(__('Done! E-mail about new pending article is sent.', $dom));
            } else {
                LogUtil::registerStatus(__('Warning! E-mail about new pending article could not be sent.', $dom));
            }
        }

        // Process the uploaded picture and copy to the upload directory (credit msshams)
        if ($modvars['picupload_enabled']) {
            // include the phpthumb library for thumbnail generation
            require_once ('pnincludes/phpthumb/ThumbLib.inc.php');
            $uploaddir = $modvars['picupload_uploaddir'] . '/';
            foreach ($pics2resize as $piccount => $key) {
                $tmp_name = $_FILES['news_files']['tmp_name'][$key];
                $name = $_FILES['news_files']['name'][$key];

                $thumb = PhpThumbFactory::create($tmp_name, array('jpegQuality' => 80));
                if ($modvars['picupload_sizing'] == '0') {
                    $thumb->Resize($modvars['picupload_picmaxwidth'],$modvars['picupload_picmaxheight']);
                } else {
                    $thumb->adaptiveResize($modvars['picupload_picmaxwidth'],$modvars['picupload_picmaxheight']);
                }
                $thumb->save($uploaddir.'pic_sid'.$sid.'-'.$piccount.'-norm.jpg', 'jpg');

                $thumb1 = PhpThumbFactory::create($tmp_name);
                if ($modvars['picupload_sizing'] == '0') {
                    $thumb1->Resize($modvars['picupload_thumbmaxwidth'],$modvars['picupload_thumbmaxheight']);
                } else {
                    $thumb1->adaptiveResize($modvars['picupload_thumbmaxwidth'],$modvars['picupload_thumbmaxheight']);
                }
                $thumb1->save($uploaddir.'pic_sid'.$sid.'-'.$piccount.'-thumb.jpg', 'jpg');

                // for index page picture create an extra thumbnail
                if ($piccount==0){
                    $thumb2 = PhpThumbFactory::create($tmp_name);
                    if ($modvars['picupload_sizing'] == '0') {
                        $thumb2->Resize($modvars['picupload_thumb2maxwidth'],$modvars['picupload_thumb2maxheight']);
                    } else {
                        $thumb2->adaptiveResize($modvars['picupload_thumb2maxwidth'],$modvars['picupload_thumb2maxheight']);
                    }
                    $thumb2->save($uploaddir.'pic_sid'.$sid.'-'.$piccount.'-thumb2.jpg', 'jpg');
                }
            }
            if ($picsuploaded != count($pics2resize) && SecurityUtil::checkPermission('News::', '::', ACCESS_ADD)) {
                LogUtil::registerStatus(_fn('%s out of %s picture was uploaded and resized. Article now has draft status, since not all pictures were uploaded.', '%s out of %s pictures were uploaded and resized. Article now has draft status, since not all pictures were uploaded.', $picsuploaded, array(count($pics2resize), $picsuploaded), $dom));
            } else {
                LogUtil::registerStatus(_fn('%s out of %s picture was uploaded and resized.', '%s out of %s pictures were uploaded and resized.', $picsuploaded, array(count($pics2resize), $picsuploaded), $dom));
            }
        }
    }

    return pnRedirect(pnModURL('News', $referertype, 'view'));
}

/**
 * view items
 *
 * @author Mark West
 * @param 'page' starting number for paged view
 * @return string HTML string
 */
function News_user_view($args = array())
{
    // Security check
    if (!SecurityUtil::checkPermission('News::', '::', ACCESS_OVERVIEW)) {
        return LogUtil::registerPermissionError();
    }

    $dom = ZLanguage::getModuleDomain('News');

    // clean the session preview data
    SessionUtil::delVar('newsitem');

    // get all module vars for later use
    $modvars = pnModGetVar('News');

    // Get parameters from whatever input we need
    $page         = isset($args['page']) ? $args['page'] : (int)FormUtil::getPassedValue('page', 1, 'GET');
    $prop         = isset($args['prop']) ? $args['prop'] : (string)FormUtil::getPassedValue('prop', null, 'GET');
    $cat          = isset($args['cat']) ? $args['cat'] : (string)FormUtil::getPassedValue('cat', null, 'GET');
    $itemsperpage = isset($args['itemsperpage']) ? $args['itemsperpage'] : (int)FormUtil::getPassedValue('itemsperpage', $modvars['itemsperpage'], 'GET');

    // work out page size from page number
    $startnum = (($page - 1) * $itemsperpage) + 1;

    // default hideonindex argument
    $args['hideonindex'] = isset($args['hideonindex']) ? (int)$args['hideonindex'] : null;

    $lang = ZLanguage::getLanguageCode();

    // check if categorization is enabled
    if ($modvars['enablecategorization']) {
        if (!Loader::loadClass('CategoryUtil') || !Loader::loadClass('CategoryRegistryUtil')) {
            return LogUtil::registerError(__f('Error! Could not load [%s] class.', 'CategoryUtil | CategoryRegistryUtil', $dom));
        }
        // get the categories registered for News
        $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'news');
        $properties = array_keys($catregistry);

        // validate the property
        // and build the category filter - mateo
        if (!empty($prop) && in_array($prop, $properties) && !empty($cat)) {
            if (!is_numeric($cat)) {
                $rootCat = CategoryUtil::getCategoryByID($catregistry[$prop]);
                $cat = CategoryUtil::getCategoryByPath($rootCat['path'].'/'.$cat);
            } else {
                $cat = CategoryUtil::getCategoryByID($cat);
            }
            $catname = isset($cat['display_name'][$lang]) ? $cat['display_name'][$lang] : $cat['name'];

            if (!empty($cat) && isset($cat['path'])) {
                // include all it's subcategories and build the filter
                $categories = CategoryUtil::getCategoriesByPath($cat['path'], '', 'path');
                $catstofilter = array();
                foreach ($categories as $category) {
                    $catstofilter[] = $category['id'];
                }
                $catFilter = array($prop => $catstofilter);
            } else {
                LogUtil::registerError(__('Error! Invalid category passed.', $dom));
            }
        }
    }

    // get matching news articles
    $items = pnModAPIFunc('News', 'user', 'getall',
                          array('startnum'     => $startnum,
                                'numitems'     => $itemsperpage,
                                'status'       => 0,
                                'hideonindex'  => $args['hideonindex'],
                                'filterbydate' => true,
                                'category'     => isset($catFilter) ? $catFilter : null,
                                'catregistry'  => isset($catregistry) ? $catregistry : null));

    if ($items == false) {
        if ($modvars['enablecategorization'] && isset($catFilter)) {
            LogUtil::registerStatus(__f('No articles currently published under the \'%s\' category.', $catname, $dom));
        } else {
            LogUtil::registerStatus(__('No articles currently published.', $dom));
        }
    }

    // Create output object
    $render = & pnRender::getInstance('News');

    // assign various useful template variables
    $render->assign('startnum', $startnum);
    $render->assign('lang', $lang);
    $render->assign($modvars);
    $render->assign('shorturls', pnConfigGetVar('shorturls'));
    $render->assign('shorturlstype', pnConfigGetVar('shorturlstype'));

    // assign the root category
    $render->assign('category', $cat);
    $render->assign('catname', isset($catname) ? $catname : null);

    $newsitems = array();
    // Loop through each item and display it
    foreach ($items as $item)
    {
        // display if it's published and the hideonindex match (if set)
        if (($item['published_status'] == 0) &&
           (!isset($args['hideonindex']) || $item['hideonindex'] == $args['hideonindex'])) {

            // $info is array holding raw information.
            // Used below and also passed to the theme - jgm
            $info = pnModAPIFunc('News', 'user', 'getArticleInfo', $item);

            // $links is an array holding pure URLs to
            // specific functions for this article.
            // Used below and also passed to the theme - jgm
            $links = pnModAPIFunc('News', 'user', 'getArticleLinks', $info);

            // $preformat is an array holding chunks of
            // preformatted text for this article.
            // Used below and also passed to the theme - jgm
            $preformat = pnModAPIFunc('News', 'user', 'getArticlePreformat',
                                       array('info' => $info,
                                             'links' => $links));

            $render->assign(array('info' => $info,
                                  'links' => $links,
                                  'preformat' => $preformat));

            $newsitems[] = $render->fetch('news_user_index.htm', $item['sid']);
        }
    }

    // The items that are displayed on this overview page depend on the individual
    // user permissions. Therefor, we can not cache the whole page.
    // The single entries are cached, though.
    $render->caching = false;

    // Display the entries
    $render->assign('newsitems', $newsitems);

    // Assign the values for the smarty plugin to produce a pager
    $render->assign('pager', array('numitems' => pnModAPIFunc('News', 'user', 'countitems', 
                                                              array('status' => 0,
                                                                    'filterbydate' => true,
                                                                    'hideonindex' => isset($args['hideonindex']) ? $args['hideonindex'] : null,
                                                                    'category' => isset($catFilter) ? $catFilter : null)),
                                   'itemsperpage' => $itemsperpage));

    // Return the output that has been generated by this function
    return $render->fetch('news_user_view.htm');
}

/**
 * display item
 *
 * @author Mark West
 * @param 'sid' The article ID
 * @param 'objectid' generic object id maps to sid if present
 * @return string HTML string
 */
function News_user_display($args)
{
    // Get parameters from whatever input we need
    $sid       = (int)FormUtil::getPassedValue('sid', null, 'REQUEST');
    $objectid  = (int)FormUtil::getPassedValue('objectid', null, 'REQUEST');
    $page      = (int)FormUtil::getPassedValue('page', 1, 'REQUEST');
    $title     = FormUtil::getPassedValue('title', null, 'REQUEST');
    $year      = FormUtil::getPassedValue('year', null, 'REQUEST');
    $monthnum  = FormUtil::getPassedValue('monthnum', null, 'REQUEST');
    $monthname = FormUtil::getPassedValue('monthname', null, 'REQUEST');
    $day       = FormUtil::getPassedValue('day', null, 'REQUEST');

    // User functions of this type can be called by other modules
    extract($args);

    $dom = ZLanguage::getModuleDomain('News');

    // At this stage we check to see if we have been passed $objectid, the
    // generic item identifier
    if ($objectid) {
        $sid = $objectid;
    }

    // Validate the essential parameters
    if ((empty($sid) || !is_numeric($sid)) && (empty($title))) {
        return LogUtil::registerArgsError();
    }
    if (!empty($title)) {
        unset($sid);
    }

    // Set the default page number
    if ($page < 1 || !is_numeric($page)) {
        $page = 1;
    }

    // increment the read count
    if ($page == 1) {
        if (isset($sid)) {
            pnModAPIFunc('News', 'user', 'incrementreadcount', array('sid' => $sid));
        } else {
            pnModAPIFunc('News', 'user', 'incrementreadcount', array('title' => $title));
        }
    }

    // Create output object
    $render = & pnRender::getInstance('News');

    // For caching reasons you must pass a cache ID
    if (isset($sid)) {
        $render->cache_id = $sid.$page;
    } else {
        $render->cache_id = $title.$page;
    }

    // check out if the contents is cached.
    if ($render->is_cached('news_user_article.htm')) {
       return $render->fetch('news_user_article.htm');
    }

    // Get the news story
    if (!SecurityUtil::checkPermission('News::', "::", ACCESS_ADD)) {
        if (isset($sid)) {
            $item = pnModAPIFunc('News', 'user', 'get', 
                                 array('sid'       => $sid, 
                                       'status'    => 0));
        } else {
            $item = pnModAPIFunc('News', 'user', 'get', 
                                 array('title'     => $title,
                                       'year'      => $year,
                                       'monthname' => $monthname,
                                       'monthnum'  => $monthnum,
                                       'day'       => $day,
                                       'status'    => 0));
            $sid = $item['sid'];
            pnQueryStringSetVar('sid', $sid);
        }
    } else {
        if (isset($sid)) {
            $item = pnModAPIFunc('News', 'user', 'get', 
                                 array('sid'       => $sid));
        } else {
            $item = pnModAPIFunc('News', 'user', 'get', 
                                 array('title'     => $title,
                                       'year'      => $year,
                                       'monthname' => $monthname,
                                       'monthnum'  => $monthnum,
                                       'day'       => $day));
            $sid = $item['sid'];
            pnQueryStringSetVar('sid', $sid);
        }
    }

    if ($item === false) {
        return LogUtil::registerError(__('Error! No such article found.', $dom), 404);
    }

    // Explode the review into an array of seperate pages
    $allpages = explode('<!--pagebreak-->', $item['bodytext']);

    // Set the item bodytext to be the required page
    // nb arrays start from zero, pages from one
    $item['bodytext'] = $allpages[$page-1];
    $numpages = count($allpages);
    unset($allpages);

    // If the pagecount is greater than 1 and we're not on the frontpage
    // don't show the hometext
    if ($numpages > 1  && $page > 1) {
        $item['hometext'] = '';
    }

    // $info is array holding raw information.
    // Used below and also passed to the theme - jgm
    $info = pnModAPIFunc('News', 'user', 'getArticleInfo', $item);

    // $links is an array holding pure URLs to specific functions for this article.
    // Used below and also passed to the theme - jgm
    $links = pnModAPIFunc('News', 'user', 'getArticleLinks', $info);

    // $preformat is an array holding chunks of preformatted text for this article.
    // Used below and also passed to the theme - jgm
    $preformat = pnModAPIFunc('News', 'user', 'getArticlePreformat',
                              array('info'  => $info,
                                    'links' => $links));

    // set the page title
    if ($numpages <= 1) {
        PageUtil::setVar('title', $info['title']);
    } else {
        PageUtil::setVar('title', $info['title'] . __f(' :: page %s', $page, $dom));
    }

    // Assign the story info arrays
    $render->assign(array('info'      => $info,
                          'links'     => $links,
                          'preformat' => $preformat,
                          'page'      => $page));

    $modvars = pnModGetVar('News');
    $render->assign($modvars);
    $render->assign('lang', ZLanguage::getLanguageCode());
    
    // get more articletitles in the categories of this article
    if ($modvars['enablecategorization'] && $modvars['enablemorearticlesincat']) {
        // check how many articles to display
        if ($modvars['morearticlesincat'] > 0) {
            $morearticlesincat = $modvars['morearticlesincat'];
        } elseif ($modvars['morearticlesincat'] == 0 && array_key_exists('morearticlesincat', $info['attributes'])) {
            $morearticlesincat = $info['attributes']['morearticlesincat'];
        } else {
            $morearticlesincat = 0;
        }
        if ($morearticlesincat > 0) {
            if (!Loader::loadClass('CategoryUtil') || !Loader::loadClass('CategoryRegistryUtil')) {
                return LogUtil::registerError(__f('Error! Could not load [%s] class.', 'CategoryUtil | CategoryRegistryUtil', $dom));
            }
            // get the categories registered for News
            $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'news');
            foreach (array_keys($catregistry) as $property) {
                $catFilter[$property] = $info['categories'][$property]['id'];
            }
            // get matching news articles
            // TODO exclude current article, query does not work yet :-(
            $morearticlesincat = pnModAPIFunc('News', 'user', 'getall',
                                  array('numitems'     => $morearticlesincat,
                                        'status'       => 0,
                                        'filterbydate' => true,
                                        'category'     => $catFilter,
                                        'catregistry'  => $catregistry,
                                        'query'        => array('sid', '!=', $sid)));
            $render->assign('morearticlesincat', $morearticlesincat);
        }
    }

    // Now lets assign the informatation to create a pager for the review
    $render->assign('pager', array('numitems'     => $numpages,
                                   'itemsperpage' => 1));

    // Return the output that has been generated by this function
    return $render->fetch('news_user_article.htm');
}

/**
 * display article archives
 *
 * @author Andreas Krapohl
 * @author Mark West
 * @return string HTML string
 */
function News_user_archives($args)
{
    // Get parameters from whatever input we need
    $year  = (int)FormUtil::getPassedValue('year', null, 'REQUEST');
    $month = (int)FormUtil::getPassedValue('month', null, 'REQUEST');
    $day   = '31';

    // Security check
    if (!SecurityUtil::checkPermission('News::', '::', ACCESS_OVERVIEW)) {
        return LogUtil::registerPermissionError();
    }

    $dom = ZLanguage::getModuleDomain('News');

    // Dates validation
    $currentdate = explode(',', DateUtil::getDatetime('', '%Y,%m,%d'));
    if (!empty($year) || !empty($month)) {
        if ((empty($year) || empty($month)) ||
            ($year > (int)$currentdate[0] || ($year == (int)$currentdate[0] && $month > (int)$currentdate[1]))) {
                pnRedirect(pnModURL('News', 'user', 'archives'));
        } elseif ($year == (int)$currentdate[0] && $month == (int)$currentdate[1]) {
            $day = (int)$currentdate[2];
        }
    }

    // Load localized month names
    $monthnames = explode(' ', __('January February March April May June July August September October November December', $dom));

    // Create output object
    $cacheid = "$month|$year";
    $render = & pnRender::getInstance('News', null, $cacheid);

    // output vars
    $archivemonths = array();
    $archiveyears = array();

    if (!empty($year) && !empty($month)) {
        $items = pnModAPIFunc('News', 'user', 'getall',
                              array('order'  => 'from',
                                    'from'   => "$year-$month-01 00:00:00",
                                    'to'     => "$year-$month-$day 23:59:59",
                                    'status' => 0));
        $render->assign('year', $year);
        $render->assign('month', $monthnames[$month-1]);

    } else {
        // get all matching news articles
        $monthsyears = pnModAPIFunc('News', 'user', 'getMonthsWithNews');

        foreach ($monthsyears as $monthyear) {
            $month = DateUtil::getDatetime_Field($monthyear, 2);
            $year  = DateUtil::getDatetime_Field($monthyear, 1);
            $dates[$year][] = $month;
        }
        foreach ($dates as $year => $years) {
            foreach ($years as $month)
            {
                //$linktext = $monthnames[$month-1]." $year";
                $linktext = $monthnames[$month-1];
                $nrofarticles = pnModAPIFunc('News', 'user', 'countitems',
                                             array('from'   => "$year-$month-01 00:00:00",
                                                   'to'     => "$year-$month-$day 23:59:59",
                                                   'status' => 0));

                $archivemonths[$year][$month] = array('url'          => pnModURL('News', 'user', 'archives', array('month' => $month, 'year' => $year)),
                                                      'title'        => $linktext,
                                                      'nrofarticles' => $nrofarticles);
            }
        }
        $items = false;
    }

    $render->assign('archivemonths', $archivemonths);
    $render->assign('archiveitems', $items);
    $render->assign('enablecategorization', pnModGetVar('News', 'enablecategorization'));

    // Return the output that has been generated by this function
    return $render->fetch('news_user_archives.htm');
}

/**
 * preview article
 *
 * @author Mark West
 * @return string HTML string
 */
function News_user_preview($args)
{
    // Get parameters from whatever input we need
    $title               = FormUtil::getPassedValue('title', null, 'REQUEST');
    $hometext            = FormUtil::getPassedValue('hometext', null, 'REQUEST');
    $hometextcontenttype = FormUtil::getPassedValue('hometextcontenttype', null, 'REQUEST');
    $bodytext            = FormUtil::getPassedValue('bodytext', null, 'REQUEST');
    $bodytextcontenttype = FormUtil::getPassedValue('bodytextcontenttype', null, 'REQUEST');
    $notes               = FormUtil::getPassedValue('notes', null, 'REQUEST');

    // User functions of this type can be called by other modules
    extract($args);

    // format the contents if needed
    if ($hometextcontenttype == 0) {
        $hometext = nl2br($hometext);
    }
    if ($bodytextcontenttype == 0) {
        $bodytext = nl2br($bodytext);
    }

    $render = & pnRender::getInstance('News', false);

    $render->assign('preview', array('title'    => $title,
                                     'hometext' => $hometext,
                                     'bodytext' => $bodytext,
                                     'notes'    => $notes));

    return $render->fetch('news_user_preview.htm');
}

/**
 * display available categories in News
 *
 * @author Erik Spaan [espaan]
 * @return string HTML string
 */
function News_user_categorylist($args)
{
    // Security check
    if (!SecurityUtil::checkPermission('News::', '::', ACCESS_OVERVIEW)) {
        return LogUtil::registerPermissionError();
    }
    
    $dom = ZLanguage::getModuleDomain('News');

    // Create output object
    $render = & pnRender::getInstance('News');

    $enablecategorization = pnModGetVar('News', 'enablecategorization');
    if (pnUserLoggedIn()) {
        $uid = SessionUtil::getVar('uid');
    } else {
        $uid = 0;
    }
   
    if ($enablecategorization) {
        if (!Loader::loadClass ('CategoryRegistryUtil') || !Loader::loadClass ('CategoryUtil')) {
            return LogUtil::registerError(__f('Error! Could not load [%s] class.', 'CategoryUtil | CategoryRegistryUtil', $dom));
        }
        // Get the categories registered for News
        $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories('News', 'news');
        $properties  = array_keys($catregistry);
        $propertiesdata = array();
        foreach ($properties as $property)
        {
            $rootcat = CategoryUtil::getCategoryByID($catregistry[$property]);
            if (!empty($rootcat)) {
                $rootcat['path'] .= '/';
                // Get all categories in this category property
                $catcount = _countcategories($rootcat, $property, $catregistry, $uid);
                $rootcat['news_articlecount'] = $catcount['category']['news_articlecount'];
                $rootcat['news_totalarticlecount'] = $catcount['category']['news_totalarticlecount'];
                $rootcat['news_yourarticlecount'] = $catcount['category']['news_yourarticlecount'];
                $rootcat['subcategories'] = $catcount['subcategories'];
                // Store data per property for listing in the overview
                $propertiesdata[] = array('name'     => $property,
                                          'category' => $rootcat);
            }
        }
        // Assign property & category related vars
        $render->assign('propertiesdata', $propertiesdata);
    }

    // Assign the config vars
    $render->assign('enablecategorization', $enablecategorization);
    $render->assign('shorturls', pnConfigGetVar('shorturls'));
    $render->assign('shorturlstype', pnConfigGetVar('shorturlstype'));
    $render->assign('lang', ZLanguage::getLanguageCode());
    $render->assign('catimagepath', pnModGetVar('News', 'catimagepath'));

    // Return the output that has been generated by this function
    return $render->fetch('news_user_categorylist.htm');
}

/**
 * display article as pdf 
 *
 * @author Erik Spaan
 * @param 'sid' The article ID
 * @param 'objectid' generic object id maps to sid if present
 * @return string HTML string
 */
function News_user_displaypdf($args)
{
    // Get parameters from whatever input we need
    $sid       = (int)FormUtil::getPassedValue('sid', null, 'REQUEST');
    $objectid  = (int)FormUtil::getPassedValue('objectid', null, 'REQUEST');
    $title     = FormUtil::getPassedValue('title', null, 'REQUEST');
    $year      = FormUtil::getPassedValue('year', null, 'REQUEST');
    $monthnum  = FormUtil::getPassedValue('monthnum', null, 'REQUEST');
    $monthname = FormUtil::getPassedValue('monthname', null, 'REQUEST');
    $day       = FormUtil::getPassedValue('day', null, 'REQUEST');

    // User functions of this type can be called by other modules
    extract($args);

    $dom = ZLanguage::getModuleDomain('News');

    // get all module vars for later use
    $modvars = pnModGetVar('News');

    // At this stage we check to see if we have been passed $objectid, the
    // generic item identifier
    if ($objectid) {
        $sid = $objectid;
    }

    // Validate the essential parameters
    if ((empty($sid) || !is_numeric($sid)) && (empty($title))) {
        return LogUtil::registerArgsError();
    }
    if (!empty($title)) {
        unset($sid);
    }

    // Include the TCPDF class from the configured path
    Loader::includeOnce($modvars['pdflink_tcpdfpath']);
    Loader::includeOnce($modvars['pdflink_tcpdflang']);

    // Create output object
    $render = & pnRender::getInstance('News');

    // Get the news story
    if (isset($sid)) {
        $item = pnModAPIFunc('News', 'user', 'get', 
                             array('sid'       => $sid, 
                                   'status'    => 0));
    } else {
        $item = pnModAPIFunc('News', 'user', 'get', 
                             array('title'     => $title,
                                   'year'      => $year,
                                   'monthname' => $monthname,
                                   'monthnum'  => $monthnum,
                                   'day'       => $day,
                                   'status'    => 0));
        $sid = $item['sid'];
        pnQueryStringSetVar('sid', $sid);
    }
    if ($item === false) {
        return LogUtil::registerError(__('Error! No such article found.', $dom), 404);
    }

    // Explode the review into an array of seperate pages
    $allpages = explode('<!--pagebreak-->', $item['bodytext']);

    // Set the item hometext to be the required page
    // nb arrays start from zero, pages from one
    //$item['bodytext'] = $allpages[$page-1];
    $numpages = count($allpages);
    //unset($allpages);

    // $info is array holding raw information.
    $info = pnModAPIFunc('News', 'user', 'getArticleInfo', $item);

    // $links is an array holding pure URLs to specific functions for this article.
    $links = pnModAPIFunc('News', 'user', 'getArticleLinks', $info);

    // $preformat is an array holding chunks of preformatted text for this article.
    $preformat = pnModAPIFunc('News', 'user', 'getArticlePreformat',
                              array('info'  => $info,
                                    'links' => $links));

    // Assign the story info arrays
    $render->assign(array('info'      => $info,
                          'links'     => $links,
                          'preformat' => $preformat));

    $render->assign('enablecategorization', $modvars['enablecategorization']);
    $render->assign('catimagepath', $modvars['catimagepath']);
    $render->assign('pdflink', $modvars['pdflink']);

    // Store output in variable
    $articlehtml = $render->fetch('news_user_articlepdf.htm');
    
    // create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false); 

    // set pdf document information
    $pdf->SetCreator(pnConfigGetVar('sitename'));
    $pdf->SetAuthor($info['contributor']);
    $pdf->SetTitle($info['title']);
    $pdf->SetSubject($info['cattitle']);
    //$pdf->SetKeywords($info['cattitle']);

    // set default header data
    //$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
    $sitename = pnConfigGetVar('sitename');
/*    $pdf->SetHeaderData(
                $modvars['pdflink_headerlogo'],
                $modvars['pdflink_headerlogo_width'],
                __f('Article %1$s by %2$s', array($info['title'], $info['contributor']), $dom),
                $sitename . ' :: ' . __('News publisher', $dom));*/
    $pdf->SetHeaderData(
                $modvars['pdflink_headerlogo'],
                $modvars['pdflink_headerlogo_width'], 
                '', 
                $sitename . ' :: ' . $info['cattitle']. ' :: ' . $info['topicname']);
    // set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    // set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    //set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    //set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    //set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO); 
    //set some language-dependent strings
    $pdf->setLanguageArray($l); 

    // set font, freeserif is big !
    //$pdf->SetFont('freeserif', '', 10);
    // For Unicode data put dejavusans in tcpdf_config.php
    $pdf->SetFont(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN);

    // add a page
    $pdf->AddPage();

    // output the HTML content
    $pdf->writeHTML($articlehtml, true, 0, true, 0);

    // reset pointer to the last page
    $pdf->lastPage();

    //Close and output PDF document
    $pdf->Output($info['urltitle'].'.pdf', 'I');

    // Since the output doesn't need the theme wrapped around it, 
    // let the theme know that the function is already finished
    return true;
}

/**
 * Internal function to count categories including subcategories
 *
 * @author Erik Spaan [espaan]
 * @return array
 */
function _countcategories($category, $property, $catregistry, $uid)
{
    // Get the number of articles in this category within this category property
    $news_articlecount = pnModAPIFunc('News', 'user', 'countitems',
                                      array('status'       => 0,
                                            'filterbydate' => true,
                                            'category'     => array($property => $category['id']),
                                            'catregistry'  => $catregistry));

    $news_totalarticlecount = $news_articlecount;

    // Get the number of articles by the current uid in this category within this category property
    if ($uid > 0) {
        $news_yourarticlecount = pnModAPIFunc('News', 'user', 'countitems',
                                              array('status'       => 0,
                                                    'filterbydate' => true,
                                                    'uid'          => $uid,
                                                    'category'     => array($property => $category['id']),
                                                    'catregistry'  => $catregistry));
    } else {
        $news_yourarticlecount = 0;
    }

    // Check if this category is a leaf/endnode
    $subcats = CategoryUtil::getCategoriesByParentID($category['id']);
    if (!$category['is_leaf'] && !empty($subcats)) {
        $subcategories = array();
        foreach ($subcats as $cat) {
            $count = _countcategories($cat, $property, $catregistry, $uid);
            // Add the subcategories count to this category
            $news_totalarticlecount += $count['category']['news_totalarticlecount'];
            $news_yourarticlecount  += $count['category']['news_yourarticlecount'];
            $subcategories[] = $count;
        }
    } else {
        $subcategories = null;
    }

    $category['news_articlecount'] = $news_articlecount;
    $category['news_totalarticlecount'] = $news_totalarticlecount;
    $category['news_yourarticlecount'] = $news_yourarticlecount;
    // if a category image is available, store it for easy reuse
    if (isset($category['__ATTRIBUTES__']) && isset($category['__ATTRIBUTES__']['topic_image'])) {
        $category['catimage'] = $category['__ATTRIBUTES__']['topic_image'];
    }

    $return = array('category'      => $category,
                    'subcategories' => $subcategories);

    return $return;
}