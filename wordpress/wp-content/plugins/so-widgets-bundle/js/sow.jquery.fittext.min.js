/*!
 * FitText.js 1.2
 *
 * Copyright 2011, Dave Rupert http://daverupert.com
 * Released under the WTFPL license
 * http://sam.zoy.org/wtfpl/
 *
 * Date: Thu May 05 14:23:00 2011 -0600
 */
var sowb=window.sowb||{};!function(t){t.fn.fitText=function(i,e){var n=i||1,o=t.extend({minFontSize:Number.NEGATIVE_INFINITY,maxFontSize:Number.POSITIVE_INFINITY},e);return this.each(function(){var i=t(this),e=i.data("fitTextData")||{};if(e.initialized)e.resizer();else{var a=function(){var t=i.width();t&&i.css("font-size",Math.max(Math.min(t/(10*n),parseFloat(o.maxFontSize)),parseFloat(o.minFontSize)))};e.initialized=!0,e.resizer=a,i.data("fitTextData",e),a(),t(window).on("resize.fittext orientationchange.fittext",a),t(sowb).on("setup_widgets",a)}})}}(jQuery),jQuery(function(t){sowb.runFitText=function(){t(".so-widget-fittext-wrapper").each(function(){var i=t(this);if(!i.is(":visible"))return i;var e=i.data("fitTextCompressor")||.85,n=i.data("fitTextDone");i.find("h1,h2,h3,h4,h5,h6").each(function(){var i=t(this);i.fitText(e,{minFontSize:"12px",maxFontSize:i.css("font-size")})}),n||(i.data("fitTextDone",!0),i.trigger("fitTextDone"))})},t(window).on("resize",sowb.runFitText),t(window).on("load",sowb.runFitText),t(sowb).on("setup_widgets",sowb.runFitText),document.fonts&&document.fonts.ready&&document.fonts.ready.then(sowb.runFitText),sowb.runFitText()}),window.sowb=sowb;