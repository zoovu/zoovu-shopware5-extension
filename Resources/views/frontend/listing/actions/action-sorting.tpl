{namespace name="frontend/listing/listing_actions"}
{extends file='parent:frontend/listing/actions/action-sorting.tpl'}
{block name='frontend_listing_actions_sort_field'}
	{if !$semknoxSearchResults}{$smarty.block.parent}{else}
		{$listingMode = {config name=listingMode}}

		<div class="sort--select select-field">
			<select name="{$shortParameters.sSort}" class="sort--field action--field" data-auto-submit="true" {if $listingMode != 'full_page_reload'}data-loadingindicator="false"{/if}>
				{foreach $semknoxSearchResults.semknoxOrder as $orderitem}
						<option value="{$orderitem.name}" {if $orderitem.active eq 1}selected="selected"{/if}>{$orderitem.viewName}</option>

						{block name='frontend_listing_actions_sort_values'}{/block}
				{/foreach}
			</select>
		</div>
	{/if}
{/block}