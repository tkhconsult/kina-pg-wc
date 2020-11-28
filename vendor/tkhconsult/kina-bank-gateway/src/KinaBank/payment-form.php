<?php
    /**
     * @var string $formId
     * @var string $formName
     * @var string $formMethod
     * @var string $formAction
     * @var string $elements
     * @var bool $autoSubmit
     */
?>
<script src="https://devegateway.kinabank.com.pg/kina/js/kbl-ec.js"></script>
<link href="https://devegateway.kinabank.com.pg/kina/css/kbl-ec.css" rel="stylesheet">
<form
    id="<?php echo $formId; ?>"
    name="<?php echo $formName; ?>"
    method="<?php echo $formMethod; ?>"
    action="<?php echo $formAction; ?>"
    enctype="application/x-www-form-urlencoded"
    target="kblpaymentiframe"
>
    <button class="btn btn-primary btn-lg" type="button" onclick="submitPaymentForm('card')">Checkout - Credit/Debit Cards</button>
    <button class="btn btn-primary btn-lg" type="button" onclick="submitPaymentForm('bank')">Checkout - Bank Transfer</button>
    <?php
        echo $elements;
    ?>
</form><br/>
<div id="kbliframediv" class="kbliframeoverlay">
    <div id="kbliframeinnerdiv">
        <iframe id="kblpaymentiframe" name="kblpaymentiframe"></iframe>
    </div>
</div>
<?php if ($autoSubmit) { ?>
    <style>
        .hidden{
            visibility: hidden;
        }
    </style>

    <script type="text/javascript">
      +(function(){
        var formNode    = document.getElementById('<?php echo $formId; ?>');

        // formNode.submit();
      })();
    </script>
<?php
}