{*
* NOTICE OF LICENSE
*
* This file is licenced under the Software License Agreement.
* With the purchase or the installation of the software in your application
* you accept the licence agreement.
*
* You must not modify, adapt or create derivative works of this source code
*
*  @author    www.mergado.cz
*  @copyright 2016 Mergado technologies, s. r. o.
*  @license   LICENSE.txt
*}


<!-- Global site tag (gtag.js) - Google Analytics -->
<script>
    var gtagAnalyticsId = '{$gtag_analytics_id}';
</script>

<script async src="https://www.googletagmanager.com/gtag/js?id={$gtag_analytics_id}"></script>
{literal}
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', gtagAnalyticsId);
    </script>
{/literal}
