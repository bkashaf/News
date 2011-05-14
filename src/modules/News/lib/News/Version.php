<?php

/**
 * Zikula Application Framework
 *
 * @copyright  (c) Zikula Development Team
 * @link       http://www.zikula.org
 * @license    GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @author     Mark West [markwest]
 * @author     Mateo Tibaquira [mateo]
 * @author     Erik Spaan [espaan]
 * @category   Zikula_3rdParty_Modules
 * @package    Content_Management
 * @subpackage News
 */
class News_Version extends Zikula_AbstractVersion
{

    public function getMetaData()
    {
        $meta = array();
        $meta['displayname'] = $this->__('News publisher');
        $meta['description'] = $this->__('Provides the ability to publish and manage news articles contributed by site users, with support for news categories and various associated blocks.');
        $meta['version'] = '3.0.0';
        //! this defines the module's url
        $meta['url'] = $this->__('news');
        $meta['core_min'] = '1.3.0'; // requires minimum 1.3.0 or later
        $meta['capabilities'] = array(HookUtil::SUBSCRIBER_CAPABLE => array('enabled' => true));
        $meta['securityschema'] = array('News::' => 'Contributor ID::Article ID',
                'News:pictureupload:' => '::',
                'News:publicationdetails:' => '::');
        // Module depedencies
        $meta['dependencies'] = array(
                array('modname'    => 'Scribite',
                      'minversion' => '4.2.1',
                      'maxversion' => '',
                      'status'     => ModUtil::DEPENDENCY_RECOMMENDED),
                array('modname'    => 'EZComments',
                      'minversion' => '3.0.1',
                      'maxversion' => '',
                      'status'     => ModUtil::DEPENDENCY_RECOMMENDED),
        );
        return $meta;
    }

    protected function setupHookBundles()
    {
        $bundle = new Zikula_HookManager_SubscriberBundle($this->name, 'subscriber_area.ui.news.articles', 'ui', $this->__('News Articles Hooks'));
        $bundle->addType('ui.view', 'news.hook.articles.ui.view');
        $bundle->addType('ui.edit', 'news.hook.articles.ui.edit');
        $bundle->addType('ui.delete', 'news.hook.articles.ui.delete');
        $bundle->addType('validate.edit', 'news.hook.articles.validate.edit');
        $bundle->addType('validate.delete', 'news.hook.articles.validate.delete');
        $bundle->addType('process.edit', 'news.hook.articles.process.edit');
        $bundle->addType('process.delete', 'news.hook.articles.process.delete');
        $this->registerHookSubscriberBundle($bundle);

        $bundle = new Zikula_HookManager_SubscriberBundle($this->name, 'subscriber_area.filter.news.articlesfilter', 'filter', $this->__('News Display Hooks'));
        $bundle->addType('ui.filter', 'news.hook.articlesfilter.ui.filter');
        $this->registerHookSubscriberBundle($bundle);
    }

}