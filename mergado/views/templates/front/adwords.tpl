{if $adwords_remarketing_id != ''}
    <!-- gad remarketing -->
    <script type="text/javascript">
        /* <![CDATA[ */
        var google_conversion_id = {$adwords_remarketing_id};
        var google_custom_params = window.google_tag_params;
        var google_remarketing_only = true;
        /* ]]> */
    </script>
    <script type="text/javascript" src="//www.googleadservices.com/pagead/conversion.js">
    </script>
    <noscript>
    <div style="display:inline;">
        <img height="1" width="1" style="border-style:none;" alt="" src="//googleads.g.doubleclick.net/pagead/viewthroughconversion/{$adwords_remarketing_id}/?value=0&amp;guid=ON&amp;script=0"/>
    </div>
    </noscript>
{/if}