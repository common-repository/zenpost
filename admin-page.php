<div class="wrap">
	<h1><?php echo self::PAGE_TITLE; ?></h1>
	<form method="post" action="options.php" id="secret-form">
		<?php settings_fields( self::OPTION_GROUP ); ?>
		<?php do_settings_sections( self::OPTION_GROUP ); ?>
		
		<h3>Instructions</h3>
		<b>Step 1)</b> Copy and save the below Authentication Key to your computer.<br><br>
		<b>Step 2)</b> Go to your Zenpost <a href="http://app.zenpost.com/app#/publisher/channels/wordpress-configure" target="new">WordPress Configuration page</a>.<br><br>
		<b>Step 3)</b> Enter your Authentication Key and WordPress URL to complete the connection.<br><br>	

		<hr>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">Authentication Key</th>
					<td>
						<input id="zenAuthcode" class="zenAuthcode" type="text" name="<?php echo self::OPTION_NAME; ?>" value="<?php echo $option_value; ?>" readonly> 
						<a href="javascript:void(0)" class="copyauthcode button button-primary" data-clipboard-target="#zenAuthcode"> Copy</a> 
						<input type="submit" name="submit" id="submit" class="button" value="Regenerate">
					</td>
				</tr>
			</tbody>
		</table>

	</form>
</div><script type="text/javascript">
	jQuery(document).ready(function(){
		(function($){

			var clipboard = new ClipboardJS('.copyauthcode');

			clipboard.on('success', function(e) {
			    console.info('Action:', e.action);
			    console.info('Text:', e.text);
			    console.info('Trigger:', e.trigger);

			    e.clearSelection();
			});

			clipboard.on('error', function(e) {
			    console.error('Action:', e.action);
			    console.error('Trigger:', e.trigger);
			});


			/*function copy() {
			  var copyText = document.querySelector(".zenAuthcode");
			  copyText.select();
			  document.execCommand("copy");
			}
			document.querySelector(".copyauthcode").addEventListener("click", copy);*/
			/*$(document).on('click','.copyauthcode',function(){
				$('.zenAuthcode').val()
			});*/
		}(jQuery))
	});
</script>