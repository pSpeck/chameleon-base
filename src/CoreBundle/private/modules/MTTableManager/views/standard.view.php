<form id="cmsform" name="cmsform" method="get" action="<?=PATH_CMS_CONTROLLER; ?>" target="_top" accept-charset="UTF-8">
    <input type="hidden" name="tableid" value="<?=TGlobal::OutHTML($data['id']); ?>"/>
    <input type="hidden" name="pagedef" value="tableeditor"/>
    <input type="hidden" name="id" value=""/>
    <input type="hidden" name="module_fnc[contentmodule]" value=""/>
    <?php foreach ($data['aHiddenFields'] as $key => $value) {
    ?>
    <input type="hidden" name="<?=TGlobal::OutHTML($key); ?>" value="<?=TGlobal::OutHTML($value); ?>"/>
    <?php
} ?>
</form>
<form id="cmsformdel" name="cmsformdel" method="post" action="<?=PATH_CMS_CONTROLLER; ?>" target="_top" accept-charset="UTF-8">
    <input type="hidden" name="tableid" value="<?=TGlobal::OutHTML($data['id']); ?>"/>
    <input type="hidden" name="pagedef" value="tableeditor"/>
    <input type="hidden" name="id" value=""/>
    <input type="hidden" name="module_fnc[contentmodule]" value=""/>
    <?php foreach ($data['aHiddenFields'] as $key => $value) {
        ?>
    <input type="hidden" name="<?=TGlobal::OutHTML($key); ?>" value="<?=TGlobal::OutHTML($value); ?>"/>
    <?php
    } ?>
</form>
<form id="cmsformworkonlist" name="cmsformworkonlist" method="post" action="<?=PATH_CMS_CONTROLLER; ?>" accept-charset="UTF-8">
    <input type="hidden" name="pagedef" value="<?=$data['pagedef']; ?>"/>
    <input type="hidden" name="id" value="<?=TGlobal::OutHTML($data['id']); ?>"/>
    <input type="hidden" name="items" value=""/>
    <input type="hidden" name="module_fnc[contentmodule]" value=""/>
    <?php foreach ($data['aHiddenFields'] as $key => $value) {
        ?>
    <input type="hidden" name="<?=TGlobal::OutHTML($key); ?>" value="<?=TGlobal::OutHTML($value); ?>"/>
    <?php
    } ?>
</form>
<?php if ($data['permission_new']) {
        ?>
<div style="position: relative; top: 2px;">
    <div style="padding-left: 20px;">
        <div data-table-function-bar class="btn-group">
                <?php
                $data['oMenuItems']->GoToStart();
        /** @var $oMenuItem TCMSTableEditorMenuItem */
        while ($oMenuItem = $data['oMenuItems']->Next()) {
            echo $oMenuItem->GetMenuItemHTML();
        } ?>
        </div>
    </div>
</div>
<?php
    } ?>
<div>
    <div class="cmsBoxBorder">
    <?php echo $data['sTable']; ?>
    </div>
</div>
