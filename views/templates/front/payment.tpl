{*
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{extends "$layout"}

{block name="content"}
<div>
	<h3>{l s='ePayco Payment' mod='epayco'}:</h3>
	<ul class="alert alert-info">
		<li>{l s='Payment will be processed for Epayco.' mod='epayco'}.</li>
	</ul>
	
  <div>
    <form>
      <script
        src="https://checkout.epayco.co/checkout.js"
        class="epayco-button"
      	{foreach from=$epayco_form key=key item=value}
      		data-epayco-{$key}="{$value}"
      	{/foreach}
      >
      </script>
    </form>
  </div>
</div>
{/block}

{*block name='javascript_bottom'}
  <script type="text/javascript">
    var handler = ePayco.checkout.configure({
			key: epayco_public_key,
			test: epayco_test,
		});
    var data={
    	{foreach from=$epayco_form key=key item=value}
    		{$key}:"{$value}",
    	{/foreach}
    };
    handler.open(data);
  </script>
{/block*}