<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2001, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id: stories.php 24342 2008-06-06 12:03:14Z markwest $
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_Value_Addons
 * @subpackage News
*/

/**
 * initialise block
 *
 * @author       The Zikula Development Team
 */
function News_storiesblock_init()
{
    // Security
    pnSecAddSchema('Storiesblock::', 'Block title::');
}

/**
 * get information on block
 *
 * @author       The Zikula Development Team
 * @return       array       The block information
 */
function News_storiesblock_info()
{
    return array('text_type'       => 'Stories',
                 'module'          => 'News',
                 'text_type_long'  => 'Story Titles',
                 'allow_multiple'  => true,
                 'form_content'    => false,
                 'form_refresh'    => false,
                 'show_preview'    => true,
                 'admin_tableless' => true);
}

/**
 * display block
 *
 * @author       The Zikula Development Team
 * @param        array       $blockinfo     a blockinfo structure
 * @return       output      the rendered bock
 */
function News_storiesblock_display($blockinfo)
{
    // security check
    if (!SecurityUtil::checkPermission( 'Storiesblock::', "$blockinfo[title]::", ACCESS_READ)) {
        return;
    }

    // get the current language
    $currentlang = pnUserGetLang();

    // Break out options from our content field
    $vars = pnBlockVarsFromContent($blockinfo['content']);
    // Defaults
    if (!isset($vars['storiestype'])) {
        $vars['storiestype'] = 2;
    }
    if (!isset($vars['limit'])) {
        $vars['limit'] = 10;
    }

    // work out the paraemters for the api all
    $apiargs = array();
    switch ($vars['storiestype']) {
        case 1: //non frontpage
            $apiargs['ihome'] = 1;
            break;
        case 3:
            $apiargs['ihome'] = 0;
            break;
    }
    $apiargs['numitems'] = $vars['limit'];
    $apiargs['ignorecats'] = true;
    $apiargs['category'] = array('Main' => $vars['category']);

    // call the api
    $items = pnModAPIFunc('News', 'user', 'getall', $apiargs);

    // check for an empty return
    if (empty($items)) {
        return;
    }

    // create the output object
    $pnRender = pnRender::getInstance('News');

    // loop through the items
    $storiesoutput = array();
    foreach ($items as $item) {
        $pnRender->assign($item);
        $storiesoutput[] = $pnRender->fetch('news_block_stories_row.htm', $item['sid'], null, false, false);
    }

    // turn of caching and assign the results of
    $pnRender->caching = false;
    $pnRender->assign('stories', $storiesoutput);

    $blockinfo['content'] = $pnRender->fetch('news_block_stories.htm');
    return pnBlockThemeBlock($blockinfo);
}

/**
 * modify block settings
 *
 * @author       The Zikula Development Team
 * @param        array       $blockinfo     a blockinfo structure
 * @return       output      the bock form
 */
function News_storiesblock_modify($blockinfo)
{
    // Break out options from our content field
    $vars = pnBlockVarsFromContent($blockinfo['content']);

    // Defaults
    if (empty($vars['storiestype'])) {
        $vars['storiestype'] = 2;
    }
    if (empty($vars['limit'])) {
        $vars['limit'] = 10;
    }

    // Create output object
    $pnRender = pnRender::getInstance('News', false);

    // load the categories system
    if (!($class = Loader::loadClass('CategoryRegistryUtil'))) {
        pn_exit (pnML('_UNABLETOLOADCLASS', array('s' => 'CategoryRegistryUtil')));
    }
    $mainCat  = CategoryRegistryUtil::getRegisteredModuleCategory ('News', 'stories', 'Main', 30); // 30 == /__SYSTEM__/Modules/Global
    $pnRender->assign('mainCategory', $mainCat);
    $pnRender->assign(pnModGetVar('News'));

    // assign the block vars
    $pnRender->assign($vars);

    // Return the output that has been generated by this function
    return $pnRender->fetch('news_block_stories_modify.htm');

}

/**
 * update block settings
 *
 * @author       The Zikula Development Team
 * @param        array       $blockinfo     a blockinfo structure
 * @return       $blockinfo  the modified blockinfo structure
 */
function News_storiesblock_update($blockinfo)
{
    // Get current content
    $vars = pnBlockVarsFromContent($blockinfo['content']);

    // alter the corresponding variable
    $vars['storiestype'] = FormUtil::getPassedValue('storiestype', null, 'POST');
    $vars['topic']       = FormUtil::getPassedValue('topic', null, 'POST');
    $vars['category']    = FormUtil::getPassedValue('category', null, 'POST');
    $vars['limit']       = (int)FormUtil::getPassedValue('limit', null, 'POST');

    // write back the new contents
    $blockinfo['content'] = pnBlockVarsToContent($vars);

    // clear the block cache
    $pnRender = pnRender::getInstance('News');
    $pnRender->clear_cache('news_block_stories.htm');

    return $blockinfo;
}
