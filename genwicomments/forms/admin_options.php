<?php
    if ((isset($_POST['enable_genwi_comments']))||(isset($_POST['disable_genwi_comments']))) {
?>

<div class="updated"><p><strong>
<?php

if (is_array($errors) && count($errors)) {
    _e('The following errors were reported:', 'Localization name');
    echo '<ol>'."\n";
    foreach ($errors as $err) {
        echo '<li> '.$err.'</li>'."\n";
    } 
    echo '</ol>'."\n";
} else {
    if(get_option('genwi_comments_enabled') == "true"){
        _e('Genwi Comments have been enabled', 'Localization name');
    }else{
        _e('Genwi Comments have been disabled', 'Localization name');
    }
}

?>
</strong></p></div>
<?php
    } 

?>
<div class=wrap>
  <form method="post">
    <h2>Genwi Comments</h2>
    <fieldset name="set1">
    <table width="100%" cellspacing="2" cellpadding="5" class="editform">
    <tr>
    <th width="33%" valign="top" scope="row"><?php _e('Genwi Comments for your Blog? ') ?><i><?php if(get_option('genwi_comments_enabled') == "true"): echo('Enabled');else: echo("Disabled"); endif; ?></i> </th>
    <td>
    <?php if(get_option('genwi_comments_enabled') == "true"): ?>
    <div class="submit" style="border:none;padding: 0px;margin:0px">
    <input type="submit" name="disable_genwi_comments" value="<?php
    _e('Disable Genwi Comments', 'Localization name')
        ?> »" /></div>
    <?php else: ?>
    <div class="submit" style="border:none;padding: 0px;margin:0px">
    <input type="submit" name="enable_genwi_comments" value="<?php
    _e('Enable Genwi Comments', 'Localization name')
        ?> »" /></div>
    <?php endif; ?>
    </td>
    </tr>
    </table>
    </fieldset>
  </form>
 </div> 
