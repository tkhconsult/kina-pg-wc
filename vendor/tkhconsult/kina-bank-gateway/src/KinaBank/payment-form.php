<?php
    /**
     * @var string $formName
     * @var string $formMethod
     * @var string $formAction
     * @var string $elements
     * @var string $ubmitBtn
     * @var bool $autoSubmit
     */
?>
<form
    id="<?php echo $formName; ?>-form"
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
<?php if ($autoSubmit) { ?>
    <style>
        .hidden{
            visibility: hidden;
        }
    </style>

    <script type="text/javascript">
      +(function(){
        var formNode    = document.getElementById('<?php echo $formName; ?>-form');

        formNode.submit();
      })();
    </script>
<?php
}