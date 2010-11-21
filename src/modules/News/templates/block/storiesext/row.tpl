{if $readperm}<a href="{modurl modname='News' func='display' sid=$sid}">{/if}
{if $itemnewimage}{img modname='core' set=$newimageset src=$newimagesrc __alt='New'}{/if}
{$title|safehtml}{if $titlewrapped}{$titlewraptxt|safehtml}{/if}
{if $readperm}</a>{/if}

{if $dispinfo}({if $dispuname}{gt text='by %s' tag1=$aid_uname|userprofilelink domain="module_news"}
{if $dispdate} {gt text='on %s' tag1=$from|dateformat:$dateformat domain="module_news"} {elseif $dispreads OR $dispcomments}{$dispsplitchar} {/if}{/if}
{if $dispreads}{if $counter gt 0}{gt text='%s pageview' plural='%s pageviews' count=$counter tag1=$counter domain="module_news"}{/if}{if $dispcomments}{$dispsplitchar} {/if}{/if}
{if $dispcomments and $comments gt 0}{gt text='%s comment' plural='%s comments' count=$comments tag1=$comments domain="module_news"}{/if})
{/if}

{if $disphometext}
<div class="storiesext_hometext">
    {if $hometextwrapped}
    {$hometext|truncatehtml:$maxhometextlength:''|safehtml}
    {if $readperm}<a href="{modurl modname='News' func='display' sid=$sid}">{/if}
    {$hometextwraptxt|safehtml}
    {if $readperm}</a>{/if}
{else}
    {$hometext|safehtml}
{/if}
</div>
{/if}

{* Remove this line to use the topic link and topicimage per News item -->
{if $topicsearchurl neq ''}
<div class="storiesext_news_meta"><a href="{$topicsearchurl}">{if $topicimage neq ''}<img src="{$catimagepath}{$topicimage}" alt="{$topicname|safehtml}" title="{$topicname|safehtml}" />{else}{$topicname|safehtml}{/if}</a></div>
{/if}
<!-- Remove this line to use the topic link and topicimage per News item *}