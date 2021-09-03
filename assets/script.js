jQuery(document).ready(function() {
  jQuery('#woocommerce_kinabank_testmode').change(function() {
    if(jQuery(this).prop('checked')) {
      jQuery('#woocommerce_kinabank_dev_url').prop('readonly', false);
      jQuery('#woocommerce_kinabank_prod_url').prop('readonly', true);
    } else {
      jQuery('#woocommerce_kinabank_dev_url').prop('readonly', true);
      jQuery('#woocommerce_kinabank_prod_url').prop('readonly', false);
    }
  }).trigger('change');
})