<?php
    /**
     * @var string $formId
     * @var string $formName
     * @var string $formMethod
     * @var string $formAction
     * @var string $elements
     * @var string $host
     * @var bool $autoSubmit
     * @var string $submitLabel
     * @var string $acceptUrl
     * @var bool $showAccept
     */
?>
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<script src="<?php echo $host; ?>/kina/js/kbl-ec.js"></script>
<link href="<?php echo $host; ?>/kina/css/kbl-ec.css" rel="stylesheet">
<style>
    .fg-row #kblpaymentiframe {
        margin-top: 15%;
    }
    .fg-row .we-accept {
        margin-bottom: 300px;
    }
    .we-accept {
        padding-top: 40px;
    }
    .we-accept .accept-logo {
        height: 25px;
    }
</style>
<form
    id="<?php echo $formId; ?>"
    name="<?php echo $formName; ?>"
    method="<?php echo $formMethod; ?>"
    action="<?php echo $formAction; ?>"
    enctype="application/x-www-form-urlencoded"
    target="kblpaymentiframe"
>
    <button class="btn btn-primary btn-lg alt" type="button" onclick="submitPaymentForm('card')"><?php echo $submitLabel; ?></button>
    <?php if($showAccept) { ?>
        <div class="we-accept">
            <b class="we-accept-text">We accept:-</b>
            <div class="we-accept-logo">
                <img class="accept-logo" src="<?php echo $acceptUrl; ?>" />
            </div>
        </div>
    <?php } ?>
    <?php /*/ ?><button class="btn btn-primary btn-lg alt" type="button" onclick="submitPaymentForm('bank')">Checkout - Bank Transfer</button><?php //*/ ?>
    <?php
        echo $elements;
    ?>
</form><br/>
<div id="kbliframediv" class="kbliframeoverlay" style="z-index: 9999999;">
    <!-- kbliframeinnerdivstart -->
    <div id="kbliframeinnerdiv">
        <iframe id="kblpaymentiframe" name="kblpaymentiframe"></iframe>
    </div>
    <!-- /kbliframeinnerdivend -->
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