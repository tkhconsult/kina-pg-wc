<?php
    /**
     * @var string $formId
     * @var string $formName
     * @var string $formMethod
     * @var string $formAction
     * @var string $elements
     * @var string $ubmitBtn
     * @var bool $autoSubmit
     */
?>
<form
    id="<?php echo $formId; ?>"
    name="<?php echo $formName; ?>"
    method="<?php echo $formMethod; ?>"
    action="<?php echo $formAction; ?>"
    enctype="application/x-www-form-urlencoded"
>
    <?php
        echo $elements;
        if (!$autoSubmit) {
            echo $ubmitBtn;
            echo '<br/>';
        }
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