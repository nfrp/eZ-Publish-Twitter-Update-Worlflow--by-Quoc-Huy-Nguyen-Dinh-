{* Tweet format *}
<div class="element">
    <label>{'Tweet format'|i18n( 'design/standard/workflow/eventtype/edit' )}:</label>
    <p>{'You can add #hashtags, a prefix, a suffix. &lt;message&gt; contains the dynamic part of the tweet.'|i18n( 'design/standard/workflow/eventtype/edit' )}</p>
    <p>{'Leave it empty if you only want the dynamic content + URL to be pushed to Twitter.'|i18n( 'design/standard/workflow/eventtype/edit' )}</p>
    <input type="text" name="WorkflowEvent_event_twitterstatusupdate_tweetformat_{$event.id}" value="{$event.tweet_format|wash}" size="100" />
</div>
