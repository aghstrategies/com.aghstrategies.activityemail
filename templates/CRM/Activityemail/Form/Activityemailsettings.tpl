{* HEADER *}

{* included this style .. as an experiment *}
<style type="text/css">
{literal}
/* this is an intersting idea for this section */

.table {
  width: 100%;
  display: inline-block;
}
.crm-section {
  display: inline-block;
  width: 40%;
}
.header {
  font-weight: bold;
  /* text-align: center; */
}
{/literal}
</style>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>

{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}
<div class="table">
<div class="crm-section header">
  Activity Type
</div>
<div class="crm-section header">
  Groups To Email
</div>
{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    {* <div class="label">{$form.$elementName.label}</div> *}
    {$form.$elementName.html}
    <div class="clear"></div>
  </div>
{/foreach}
</div>
{* FIELD EXAMPLE: OPTION 2 (MANUAL LAYOUT)

  <div>
    <span>{$form.favorite_color.label}</span>
    <span>{$form.favorite_color.html}</span>
  </div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
