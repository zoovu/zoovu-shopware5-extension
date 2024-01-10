{namespace name="frontend/listing/box_article"}
{extends file='parent:frontend/listing/product-box/box-basic.tpl'}

{block name="frontend_listing_box_article_badges"}
{if $sArticle.semknoxSearch}
{if $sArticle.semknoxSearch.flags}
<div class="semknoxFlag">
 <ul>
{foreach $sArticle.semknoxSearch.flags as $flag}
<li>
{if $flag.isPrice}
{$flag.viewName}: {$flag.value|currency}
{else}
{$flag.viewName}: {$flag.value}
{/if}
</li>
{/foreach}
</ul>
</div>
{/if}
{/if}
{$smarty.block.parent}
{/block}