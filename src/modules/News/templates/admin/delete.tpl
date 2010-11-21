{gt text='Delete news article' assign='templatetitle'}
{include file='admin/menu.tpl'}

<div class="z-admincontainer">
    <div class="z-adminpageicon">{img modname='core' src='editdelete.gif' set='icons/large' alt=$templatetitle}</div>
    <h2>{$templatetitle}</h2>
    <p class="z-warningmsg">{gt text='Do you really want to delete this news article?'}</p>
    <form class="z-form" action="{modurl modname='News' type='admin' func='delete'}" method="post" enctype="application/x-www-form-urlencoded">
        <div>
            <input type="hidden" name="authid" value="{insert name='generateauthkey' module='News'}" />
            <input type="hidden" name="confirmation" value="1" />
            <input type="hidden" name="sid" value="{$sid|safetext}" />
            <fieldset>
                <legend>{gt text='Confirmation prompt'}</legend>
                <div class="z-formbuttons z-buttons">
                    {button class="z-btgreen" src=button_ok.gif set=icons/extrasmall __alt="Delete" __title="Delete" __text="Delete"}
                    <a class="z-btred" href="{modurl modname='News' type='admin' func='view'}">{img modname='core' src='button_cancel.gif' set='icons/extrasmall' __alt='Cancel'  __title='Cancel'} {gt text="Cancel"}</a>
                </div>
            </fieldset>
        </div>
    </form>
</div>