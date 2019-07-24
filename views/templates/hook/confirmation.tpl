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

<h3>{l s='Your order was received by us.' mod='epayco'}</h3>
<p class="alert alert-info">{l s='Please, check payment details and order state below.' mod='epayco'}<p>
<table class="table">
  <tr>
    <td>{l s='Amount Paid' mod='epayco'}</td>
    <td>{$epayco.total}</td>
  </tr>
  <tr>
    <td>{l s='Reference' mod='epayco'}</td>
    <td>{$epayco.reference}</td>
  </tr>
  <tr>
    <td>{l s='Status:' mod='epayco'}</td>
    <td>{$epayco.status}</td>
  </tr>
</table>

<hr />

{if isset($epayco.data)}
<table class="table">
  {foreach $epayco.data as $key => $value}
    {if isset($epayco.lang[$key])}
      <tr>
        <td>
          {$epayco.lang[$key]}
        </td>
        <td>
          {if $key == 'x_franchise' && isset($epayco.franchise[$value])}
            {$epayco.franchise[$value]}
          {else}
            {$value}
          {/if}
        </td>
      </tr>
    {/if}
  {/foreach}
</table>
{/if}

<p>
	{l s='An email has been sent with this information.' mod='epayco'}
	<br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='epayco'}
  <a href="{url entity=contact}">
    {l s='expert customer support team.' mod='epayco'}
  </a>
</p>
<hr />