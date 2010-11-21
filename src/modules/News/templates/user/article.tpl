{* For ajax modify and image uploading *}
{if $picupload_enabled}
{ajaxheader imageviewer="true"}
{* if $enableajaxedit}
{pageaddvar name='javascript' value='modules/News/javascript/multifile.js'}
{/if *}
{/if}

<script type="text/javascript">
// <![CDATA[
  var string_show = "{{gt text='Show'}}";
  var string_hide = "{{gt text='Hide'}}";
// ]]>
</script>
{nocache}{include file='user/menu.tpl'}{/nocache}
{insert name='getstatusmsg'}

<div id="news_articlecontent">
    {include file='user/articlecontent.tpl'}
</div>
<div id="news_modify">&nbsp;</div>

{if !empty($morearticlesincat)}
<div id="news_morearticlesincat">
<h4>{gt text='More articles in category '}
{foreach name='categorynames' from=$preformat.categorynames item='categoryname'}
{$categoryname}{if $smarty.foreach.categorynames.last neq true}&nbsp;&amp;&nbsp;{/if}
{/foreach}</h4>
<ul>
    {foreach from=$morearticlesincat item='morearticle'}
    <li><a href="{modurl modname='News' func='display' sid=$morearticle.sid}">{$morearticle.title|safehtml}</a> ({gt text='by %1$s on %2$s' tag1=$morearticle.contributor tag2=$morearticle.from|dateformat:'datebrief'})</li>
    {/foreach}
</ul>
</div>
{/if}

{* the next code is to display any hooks (e.g. comments, ratings). All hooks are stored in $hooks and called individually. EZComments is not called when Commenting is not allowed *}
{* modurl modname='News' func='display' sid=$info.sid assign='returnurl'}
{modcallhooks hookobject='item' hookaction='display' hookid=$info.sid module='News' returnurl=$returnurl owneruid=$info.cr_uid implode=false}
{foreach from=$hooks key='hookname' item='hook'}
{if $hookname neq 'EZComments' or $info.disallowcomments eq 0}
{$hook}
{/if}
{/foreach *}