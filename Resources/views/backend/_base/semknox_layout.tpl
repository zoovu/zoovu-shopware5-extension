<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{link file="backend/_resources/css/bootstrap33.min.css"}">
    {block name="content/header_tags"}{/block}
</head>

<body role="document" style="padding-top: 80px">

    <div class="contents">
        {block name="content/semknox_layout/main"}{/block}
    </div>


{block name="content/semknox_layout/javascriptfix"}
<script type="text/javascript" src="{link file="backend/base/frame/postmessage-api.js"}"></script>
<script type="text/javascript" src="{link file="backend/_resources/js/jquery-2.1.4.min.js"}"></script>
<script type="text/javascript" src="{link file="backend/_resources/js/bootstrap.min.js"}"></script>
{/block}
{block name="content/semknox_layout/javascript"}
<script type="text/javascript">
    $(function() {
        $('.title-form').on('submit', function(event) {
            var $this = $(this),
                values = $this.serializeArray().reduce(function(obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});
            event.preventDefault();

            postMessageApi.window.setTitle(values.title);
        });

        $('.get-window-width-form').on('submit', function(event) {
            var $this = $(this),
                $display = $this.find('input[name="window-width"]');

            event.preventDefault();

            postMessageApi.window.getWidth(function(width) {
                $display.val(width + 'px');
            });
        });

        $('.set-window-width-form').on('submit', function(event) {
            var $this = $(this),
                values = $this.serializeArray().reduce(function(obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});
            event.preventDefault();

            postMessageApi.window.setWidth(values['window-width']);
        });

        $('.get-window-height-form').on('submit', function(event) {
            var $this = $(this),
                $display = $this.find('input[name="window-height"]');

            event.preventDefault();

            postMessageApi.window.getHeight(function(width) {
                $display.val(width + 'px');
            });
        });

        $('.set-window-height-form').on('submit', function(event) {
            var $this = $(this),
                values = $this.serializeArray().reduce(function(obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});
            event.preventDefault();

            postMessageApi.window.setHeight(values['window-height']);
        });

        $('.open-module-form').on('submit', function(event) {
            var $this = $(this),
                values = $this.serializeArray().reduce(function(obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});
            event.preventDefault();

            postMessageApi.openModule({
                name: 'Shopware.apps.' + values['module-name']
            });
        });

        $('.open-subwindow-form').on('submit', function(event) {
            var $this = $(this),
                values = $this.serializeArray().reduce(function(obj, item) {
                    obj[item.name] = item.value;
                    return obj;
                }, {});
            event.preventDefault();

            values.width = 500;
            values.height = 500;

            postMessageApi.createSubWindow(values);
        });

        $('.btn-subwindow').on('click', function() {
            var values = {
                width: 500,
                height: 500,
                component: 'customSubWindow',
                url: 'semknoxSearchBackendModule/create_sub_window',
                title: 'Plugin Konfiguration'
            };

            postMessageApi.createSubWindow(values);

            window.setTimeout(function() {
                postMessageApi.sendMessageToSubWindow({
                    component: values.component,
                    params: {
                        msg: 'A message from another galaxy beyond the sky.',
                        foo: [ 'bar', 'batz', 'foobar' ]
                    }
                });
            }, 3000);
        });

        $('.btn-minimize').on('click', function() {
           postMessageApi.window.minimize();
        });

        $('.btn-maximize').on('click', function() {
            postMessageApi.window.maximize();
        });

        $('.btn-show').on('click', function() {
            postMessageApi.window.show();
        });

        $('.btn-hide').on('click', function() {
            postMessageApi.window.hide();
        });

        $('.btn-destroy').on('click', function() {
            var response = confirm('Are you sure that you wanna destroy the module?');
            if(!response) {
                return;
            }
            postMessageApi.window.destroy();
        });
        
    });
</script>
{/block}
{block name="content/semknox_layout/javascript"}{/block}
</body>
</html>