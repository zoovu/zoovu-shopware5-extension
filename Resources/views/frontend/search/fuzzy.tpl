{extends file='parent:frontend/search/fuzzy.tpl'}
			{block name='frontend_search_headline'}
				<h1 class="search--headline">
					{s name='SearchHeadline'}{/s}
				</h1>			
				{if $semknoxSearchResults.semknoxExplanation}
				<h2 class="semknoxSubheadline">
					{$semknoxSearchResults.semknoxExplanation}
				</h2>
				{/if}
			{/block}
			