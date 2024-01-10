{if $semknoxSearchResults.semknoxSuggests}
    {foreach key=groupName item=sugGroup from=$semknoxSearchResults.semknoxSuggests}
    {if $sugGroup.showFlyout}
    {if $sugGroup.showTitle }
    <li class="result--item entry--all-results result--item-header sx-header-{$sugGroup.id}"><b><i class="icon--list" ></i>&nbsp;{$sugGroup.title}</b></li>
    {/if}
        {foreach $sugGroup.items as $cat}
        <li class="list--entry block-group result--item sx-item-{$sugGroup.id}">
            <a class="search-result--link" href="{$cat.link}">
                <span class="entry--media block">{if $cat.image}<img srcset="{$cat.image}" class="media--image">{/if}</span>
                <span class="entry--name block">{$cat.showTitle}</span>                            
            </a>
         </li>
        {/foreach}
    {/if}
    {/foreach}
{/if}