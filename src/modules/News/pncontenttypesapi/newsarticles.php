<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2001, Zikula Development Team
 * @link http://www.zikula.org
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @author Mateo Tibaquira [mateo]
 * @author Erik Spaan [espaan]
 * @package Zikula_Value_Addons
 * @subpackage News
*/

/**
 * Content plugin class for news articles
 */
class News_contenttypesapi_NewsArticlesPlugin extends contentTypeBase
{
  // indispensable vars
  var $title;
  var $categories;
  var $status;
  var $show;
  var $limit;
  var $order;
  // config flags
  var $dayslimit;
  var $maxtitlelength;
  var $titlewraptext;
  var $disphometext;
  var $maxhometextlength;
  var $hometextwraptext;
  var $dispuname;
  var $dispdate;
  var $dateformat;
  var $dispreads;
  var $dispcomments;
  var $dispsplitchar;
  var $dispnewimage;
  var $newimagelimit;
  var $newimageset;
  var $newimagesrc;
  var $linktosubmit;
  var $customtemplate;

  function getModule()
  {
      return 'News';
  }

  function getName()
  {
      return 'newsarticles';
  }

  function getTitle()
  {
      $dom = ZLanguage::getModuleDomain('News');
      return __('Recent news articles', $dom);
  }

  function getDescription()
  {
      $dom = ZLanguage::getModuleDomain('News');
      return __('Displays a specific number of news articles from one or all categories available', $dom);
  }

  function isTranslatable()
  {
      return false;
  }

  /**
   * Load the data into the object
   */
  function loadData($data)
  {
    // Get the registrered categories for the News module
    $catregistry = CategoryRegistryUtil::getRegisteredModuleCategories ('News', 'news');
    $properties = array_keys($catregistry);

    // indispensable vars
    $this->title = $data['title'];
    // Store the the seperate category related form returns in the categories array for News catfiltering
    $this->categories = array();
    foreach($properties as $prop) {
        if (!empty($data['category__'.$prop])) {
            $this->categories[$prop] = $data['category__'.$prop];
        }
    }
    $this->status = $data['status'];
    $this->show = $data['show'];
    $this->limit = $data['limit'];
    $this->order = $data['order'];
    // config flags
    $this->dayslimit = $data['dayslimit'];
    $this->maxtitlelength = $data['maxtitlelength'];
    $this->titlewraptext = $data['titlewraptext'];
    $this->disphometext = $data['disphometext'];
    $this->maxhometextlength = $data['maxhometextlength'];
    $this->hometextwraptext = $data['hometextwraptext'];
    $this->dispuname = $data['dispuname'];
    $this->dispdate = $data['dispdate'];
    $this->dateformat = $data['dateformat'];
    $this->dispreads = $data['dispreads'];
    $this->dispcomments = $data['dispcomments'];
    $this->dispsplitchar = $data['dispsplitchar'];
    $this->dispnewimage = $data['dispnewimage'];
    $this->newimagelimit = $data['newimagelimit'];
    $this->newimageset = $data['newimageset'];
    $this->newimagesrc = $data['newimagesrc'];
    $this->linktosubmit = $data['linktosubmit'];
    $this->customtemplate = $data['customtemplate'];
  }

  /**
   * Display the data to the containing Content page
   */
  function display()
  {
    // Parameters for category related items properties like topicimage
    $lang = ZLanguage::getLanguageCode();
    $topicProperty = ModUtil::getVar('News', 'topicproperty');
    $topicField = empty($topicProperty) ? 'Main' : $topicProperty;

    // work out the parameters for the News api call
    $apiargs = array();
    switch ($this->show)
    {
        case 3: // non index page articles
            $apiargs['hideonindex'] = 1;
            break;
        case 2: // index page articles
            $apiargs['hideonindex'] = 0;
            break;
        // all - doesn't need hideonindex 
    }
    $apiargs['numitems'] = $this->limit; // Nr of articles to obtain
    $apiargs['status'] = (int)$this->status; // Published status

    // Handle the sorting order
    switch ($this->order)
    {
        case 2:
            $apiargs['order'] = 'weight';
            break;
        case 3:
            $apiargs['order'] = 'random';
            break;
        case 1:
            $apiargs['order'] = 'counter';
            break;
        case 0:
        default:
            // Use News module setting, so don't set apiargs[order]
    }

    $enablecategorization = ModUtil::getVar('News', 'enablecategorization');

    // Make a category filter only if categorization is enabled in News module
    if ($enablecategorization && $this->categories != null) {
        // Get the registrered categories for the News module
        $catregistry  = CategoryRegistryUtil::getRegisteredModuleCategories ('News', 'news');
        $apiargs['catregistry'] = $catregistry;
        $apiargs['category'] = $this->categories;
    }

    // Limit the shown articles in days using DateUtil
    if ((int)$this->dayslimit>0 && $vars['order']==0) {
        $apiargs['from'] = DateUtil::getDatetime_NextDay(-$this->dayslimit);
        $apiargs['to'] = DateUtil::getDatetime();
    }

    // Apply datefiltering
    $apiargs['filterbydate'] = true;    

    // call the News api and get the requested articles with the above arguments
    $items = ModUtil::apiFunc('News', 'user', 'getall', $apiargs);

    // create the output object
    $render = Zikula_View::getInstance('News', false);

    // UserUtil is not automatically loaded, so load it now if needed and set anonymous
    if ($this->dispuname) {
        $anonymous = System::getVar('anonymous');
    }

    // check for an empty return
    if (!empty($items)) {
        // loop through the items and prepare for display
        foreach (array_keys($items) as $k)
        {
            // Get specific information from the article. It was a choice not to use the pnuserapi functions
            // GetArticleInfo, GetArticleLinks and getArticlesPreformat because of speed etc.
            // --- Check for Topic related properties like topicimage, topicsearchurl etc.
            if ($enablecategorization && !empty($items[$k]['__CATEGORIES__']) && isset($items[$k]['__CATEGORIES__'][$topicField])) {
                $items[$k]['topicid'] = $items[$k]['__CATEGORIES__'][$topicField]['id'];
                $items[$k]['topicname'] = isset($items[$k]['__CATEGORIES__'][$topicField]['display_name'][$lang]) ? $items[$k]['__CATEGORIES__'][$topicField]['display_name'][$lang] : $items[$k]['__CATEGORIES__'][$topicField]['name'];
                // set the topic image if topic_image category property exists
                $items[$k]['topicimage'] = (isset($items[$k]['__CATEGORIES__'][$topicField]['__ATTRIBUTES__']) && isset($items[$k]['__CATEGORIES__'][$topicField]['__ATTRIBUTES__']['topic_image'])) ? $items[$k]['__CATEGORIES__'][$topicField]['__ATTRIBUTES__']['topic_image'] : '';
                // set the topic description if exists
                $items[$k]['topictext'] = isset($items[$k]['__CATEGORIES__'][$topicField]['display_desc'][$lang]) ? $items[$k]['__CATEGORIES__'][$topicField]['display_desc'][$lang] : '';
                // set the path of the topic
                $items[$k]['topicpath']  = $items[$k]['__CATEGORIES__'][$topicField]['path_relative'];
                // set the url to search for this topic
                if (System::getVar('shorturls') && System::getVar('shorturlstype') == 0) {
                    $items[$k]['topicsearchurl'] = DataUtil::formatForDisplay(ModUtil::url('News', 'user', 'view', array('prop' => $topicField, 'cat' => $items[$k]['topicpath'])));
                } else {
                    $items[$k]['topicsearchurl'] = DataUtil::formatForDisplay(ModUtil::url('News', 'user', 'view', array('prop' => $topicField, 'cat' => $items[$k]['topicid'])));
                }
            } else {
                $items[$k]['topicid']    = null;
                $items[$k]['topicname']  = '';
                $items[$k]['topicimage'] = '';
                $items[$k]['topictext']  = '';
                $items[$k]['topicpath']  = '';
                $items[$k]['topicsearchurl'] = '';
            }

            // Optional new image if the difference in days from the publishing date and now < the specified limit
            $items[$k]['dispnewimage'] = ($this->dispnewimage && DateUtil::getDatetimeDiff_AsField($items[$k]['from'], DateUtil::getDatetime(), 3) < (int)$this->newimagelimit);
            // Wrap the title if needed
            if ((int)$this->maxtitlelength > 0 && strlen($items[$k]['title']) > (int)$this->maxtitlelength)  {
                // wrap the title
                $items[$k]['title'] = substr($items[$k]['title'], 0, (int)$this->maxtitlelength);
                $items[$k]['titlewrapped'] = true;
                //$items[$k]['title'] .= $this->titlewraptext;
            }
            // Get the user information from the author id
            if ($this->dispuname) {
                if ($items[$k]['cr_uid'] == 0) {
                    $items[$k]['aid_uname'] = $anonymous;
                    $items[$k]['aid_name'] = $anonymous;
                } else {
                    $user = UserUtil::getVars($items[$k]['cr_uid']);
                    $items[$k]['aid_uname'] = $user['uname'];
                    $items[$k]['aid_name'] = $user['name'];
                }
            }
            // Get the optional commentcount if EZComments is available
            // TODO
//            if ($this->dispcomments && ModUtil::available('EZComments') && ModUtil::isHooked('EZComments', 'News')) {
//                $items[$k]['comments'] = ModUtil::apiFunc('EZComments', 'user', 'countitems', array('mod' => 'News', 'objectid' => $items[$k]['sid'], 'status' => 0));
//            }
            // Optional display of the hometext (frontpage teaser)
            if ($this->disphometext) {
                if ($this->maxhometextlength>0 && strlen(strip_tags($items[$k]['hometext']))>(int)$this->maxhometextlength) {
                    $items[$k]['hometextwrapped'] = true;
                }
            }
            $items[$k]['readperm'] = (SecurityUtil::checkPermission('News::', "$items[$k][cr_uid]::$items[$k][sid]", ACCESS_READ));
        }
        if ($this->dispuname||$this->dispdate||$this->dispreads||$this->dispcomments) {
            $render->assign('dispinfo', true);
            $render->assign('dispuname', $this->dispuname);
            $render->assign('dispdate', $this->dispdate);
            $render->assign('dispreads', $this->dispreads);
            $render->assign('dispcomments', $this->dispcomments);
            $render->assign('dispsplitchar', $this->dispsplitchar);
        } else {
            $render->assign('dispinfo', false);
        }
        if ($this->dispnewimage) {
            $render->assign('newimageset', $this->newimageset);
            $render->assign('newimagesrc', $this->newimagesrc);
        }
        if ($this->disphometext) {
            $render->assign('disphometext', $this->disphometext);
            $render->assign('hometextwraptext', $this->hometextwraptext);
            $render->assign('maxhometextlength', $this->maxhometextlength);
        }
        $render->assign('titlewraptext', $this->titlewraptext);
        
    }
    $render->assign('catimagepath', ModUtil::getVar('News', 'catimagepath'));
    $render->assign('linktosubmit', $this->linktosubmit);
    $render->assign('stories', $items);
    $render->assign('title', $this->title);
    $render->assign('useshorturls', (System::getVar('shorturls') && System::getVar('shorturlstype') == 0));
    if (!empty($this->customtemplate)) {
        $template = $this->customtemplate;
    } else {
        $template = 'newsarticles_view.html';
    }
    return $render->fetch('contenttype/'.$template);
  }

  /**
   * In Content editing mode this shows the Content plugin contents
   */
  function displayEditing()
  {
    $dom = ZLanguage::getModuleDomain('News');
    $properties = array_keys($this->categories);
    $lang = ZLanguage::getLanguageCode();
    // Construct the selected categories array
    $catnames = array();
    foreach ($properties as $prop) {
        foreach ($this->categories[$prop] as $catid) {
            $cat = CategoryUtil::getCategoryByID($catid);
            $catnames[] = isset($cat['display_name'][$lang]) ? $cat['display_name'][$lang] : $cat['name'];
        }
    }
    $catname = implode(' | ',$catnames);
    $output = '<h4>' .  DataUtil::formatForDisplayHTML($this->title) . '</h4>';
    $output .= '<p>' . __f('News articles listed under the \'%s\' category', $catname, $dom) . '</p>';
    return $output;
  }

  /**
   * Load the intial data into the object
   */
  function getDefaultData()
  { 
    $dom = ZLanguage::getModuleDomain('News');

    return array('title' => '',
                 'categories' => null,
                 'status' => 0,
                 'show' => 1,
                 'limit' => 5,
                 'order' => 0,
                 'dayslimit' => 0,
                 'maxtitlelength' => 0,
                 'titlewraptext' => '...',
                 'disphometext' => false,
                 'maxhometextlength' => 300,
                 'hometextwraptext' => '['.__('Read more...', $dom).']',
                 'dispuname' => true,
                 'dispdate' => true,
                 'dateformat' => '%x',
                 'dispreads' => false,
                 'dispcomments' => false,
                 'dispsplitchar' => ',',
                 'dispnewimage' => false,
                 'newimagelimit' => 3,
                 'newimageset' => 'global',
                 'newimagesrc' => 'new_3day.gif',
                 'linktosubmit' => true,
                 'customtemplate' => '');
  }

  /**
   * Fill some variables before the editing of the plugin parameters
   */
  function startEditing(&$render)
  {
    $dom = ZLanguage::getModuleDomain('News');

    // Get the News categorization setting
    $enablecategorization = ModUtil::getVar('News', 'enablecategorization');
    // Select categories only if enabled for the News module, otherwise selector will not be shown in modify template
    if ($enablecategorization) {
        // Get the registrered categories for the News module
        $catregistry  = CategoryRegistryUtil::getRegisteredModuleCategories ('News', 'news');
        $render->assign('catregistry', $catregistry);
    }
    $render->assign('enablecategorization', $enablecategorization);

    $showoptions = array(
        array('value' => 1, 'text' => __('Show all news articles', $dom)),
        array('value' => 2, 'text' => __('Show only articles set for index page listing', $dom)),
        array('value' => 3, 'text' => __('Show only articles not set for index page listing', $dom))
    );

    $statusoptions = array(
        array('value' => 0, 'text' => __('Published', $dom)),
        array('value' => 1, 'text' => __('Rejected', $dom)),
        array('value' => 2, 'text' => __('Pending Review', $dom)),
        array('value' => 3, 'text' => __('Archived', $dom)) /*,
        array('value' => 4, 'text' => __('Draft', $dom)) */
    );

    $orderoptions = array(
        array('value' => 0, 'text' => __('News publisher setting', $dom)),
        array('value' => 1, 'text' => __('Number of pageviews', $dom)),
        array('value' => 2, 'text' => __('Article weight', $dom)),
        array('value' => 3, 'text' => __('Random', $dom))
    );

    $render->assign('showoptions', $showoptions);
    $render->assign('statusoptions', $statusoptions);
    $render->assign('orderoptions', $orderoptions);
  }

  /**
   * Optional checking of the entered data
   */
  function isValid(&$data, &$message)
  {
    /*$r = '/\?v=([-a-zA-Z0-9_]+)(&|$)/';
    if (preg_match($r, $data['url'], $matches))
    {
      $this->videoId = $data['videoId'] = $matches[1];
      return true;
    }
    $message = __('Invalid input', $dom);
    return false;*/
    return true;
  }
}

/**
 * Main function that instantiates the class
 */
function News_contenttypesapi_NewsArticles($args)
{
  return new News_contenttypesapi_NewsArticlesPlugin($args['data']);
}
