{extends file='parent:frontend/index/search.tpl'}

        {block name='frontend_index_search_field'}
            <input type="search" {if $sRequests.sSearch}value="{$sRequests.sSearch|escape}"{/if} name="sSearch" class="main-search--field" autocomplete="off" autocapitalize="off" placeholder="{s name="IndexSearchFieldPlaceholder"}{/s}" maxlength="60"  />
        {/block}