/*! For license information please see FileManager-Download.js.LICENSE.txt */
"use strict";(self.webpackChunkwpide=self.webpackChunkwpide||[]).push([[979],{25305:(t,e,r)=>{r.r(e),r.d(e,{default:()=>s});r(85636);var n=r(5051),o=r(27275),i=(r(90704),r(95226),r(29522),r(90930),r(28251),r(59784),r(29296),r(87370)),a=r(788);const c={name:"Download",props:["zip","name","destination","items","manager"],data:function(){return{max:100,downloading:!1,uniqid:null,totalSize:0,downloadedSize:0,cancelled:!1}},computed:{progress:function(){return Math.floor(this.downloadedSize/this.totalSize*100)}},mounted:function(){var t=this;return(0,o.A)((0,n.A)().mark((function e(){return(0,n.A)().wrap((function(e){for(;;)switch(e.prev=e.next){case 0:return e.next=2,t.initDownload();case 2:case"end":return e.stop()}}),e)})))()},beforeDestroy:function(){this.cancelled=!0},methods:{initDownload:function(){var t=this;return(0,o.A)((0,n.A)().mark((function e(){var r;return(0,n.A)().wrap((function(e){for(;;)switch(e.prev=e.next){case 0:return r=1===t.items.length?t.items[0]:null,e.t0=t.totalSize,e.next=4,t.getItemsSize(t.items,!0);case 4:return t.totalSize=e.t0+=e.sent,t.downloading=!0,e.next=8,t.processItems(t.items);case 8:if(t.downloading=!1,t.cancelled){e.next=22;break}if(!t.zip){e.next=19;break}return e.next=13,i.A.zipItems({name:t.name,destination:t.destination,items:[],zipaction:"store",uniqid:t.uniqid});case 13:return t.hideModal(),e.next=16,t.manager.loadFiles();case 16:t.manager.afterAction("zip",r),e.next=20;break;case 19:t.showModalDialog((0,a.__)("Download File","wpide"),(0,a.__)("Your file is ready","wpide"),(0,a.__)("Download","wpide"),(function(){window.open(t.getBatchDownloadLink(t.name,t.uniqid),"_self"),t.manager.afterAction("download",r)}));case 20:e.next=24;break;case 22:return e.next=24,i.A.batchDownloadCancelled(t.uniqid);case 24:case"end":return e.stop()}}),e)})))()},getItemsSize:function(t){var e=arguments,r=this;return(0,o.A)((0,n.A)().mark((function o(){var i,a,c,s;return(0,n.A)().wrap((function(n){for(;;)switch(n.prev=n.next){case 0:i=e.length>1&&void 0!==e[1]&&e[1],a=0,s=0;case 3:if(!(s<t.length)){n.next=16;break}if(c=t[s],!i||"dir"!==c.type){n.next=12;break}return n.t0=a,n.next=9,r.manager.getFolderSize(c);case 9:a=n.t0+=n.sent,n.next=13;break;case 12:"file"===c.type&&(a+=c.size);case 13:s++,n.next=3;break;case 16:return n.abrupt("return",a);case 17:case"end":return n.stop()}}),o)})))()},processItems:function(t){var e=this;return(0,o.A)((0,n.A)().mark((function r(){var o,a,c,s,u;return(0,n.A)().wrap((function(r){for(;;)switch(r.prev=r.next){case 0:if(t=(t=t&&t.length?t:[]).filter((function(t){return"back"!==t.type})),!(o=e.spliceIntoChunks(t,100))||!o.length){r.next=19;break}case 4:if(!(a=o.shift())||!a.length){r.next=18;break}return r.next=8,e.processChunk(a);case 8:c=r.sent,!e.uniqid&&c&&(e.uniqid=c);case 10:if("dir"!==(s=a.shift()).type){r.next=17;break}return r.next=14,i.A.getDir({dir:s.path});case 14:return u=r.sent,r.next=17,e.processItems(u.files);case 17:if(a&&a.length&&!e.cancelled){r.next=10;break}case 18:if(o&&o.length&&!e.cancelled){r.next=4;break}case 19:case"end":return r.stop()}}),r)})))()},processChunk:function(t){var e=this;return(0,o.A)((0,n.A)().mark((function r(){var o,a;return(0,n.A)().wrap((function(r){for(;;)switch(r.prev=r.next){case 0:if(o="create",e.uniqid&&(o="append"),!e.zip){r.next=8;break}return r.next=5,i.A.zipItems({name:e.name,destination:e.destination,items:t,zipaction:o,uniqid:e.uniqid});case 5:a=r.sent,r.next=11;break;case 8:return r.next=10,i.A.batchDownload({destination:e.destination,items:t,zipaction:o,uniqid:e.uniqid});case 10:a=r.sent;case 11:return r.t0=e.downloadedSize,r.next=14,e.getItemsSize(t);case 14:return e.downloadedSize=r.t0+=r.sent,r.abrupt("return",a&&a.uniqid?a.uniqid:null);case 16:case"end":return r.stop()}}),r)})))()},spliceIntoChunks:function(t,e){for(var r=[];t.length>0;){var n=t.splice(0,e);r.push(n)}return r}}};const s=(0,r(86817).A)(c,(function(){var t=this,e=t._self._c;return e("div",{staticClass:"download"},[e("b-progress",{attrs:{animated:!0,"show-value":!0,max:t.max,height:"1.5rem"}},[e("b-progress-bar",{attrs:{value:t.progress,label:"".concat((t.progress/t.max*100).toFixed(0),"%")}})],1)],1)}),[],!1,null,null,null).exports},25539:(t,e,r)=>{var n=r(96024);r(50135)(n.JSON,"JSON",!0)},73015:(t,e,r)=>{r(50135)(Math,"Math",!0)},64984:(t,e,r)=>{r(52798)({target:"Object",stat:!0,sham:!r(46724)},{create:r(36448)})},54531:(t,e,r)=>{var n=r(52798),o=r(75735),i=r(87085),a=r(27403),c=r(36635);n({target:"Object",stat:!0,forced:o((function(){a(1)})),sham:!c},{getPrototypeOf:function(t){return a(i(t))}})},73412:(t,e,r)=>{var n=r(46724),o=r(67426),i=r(68666),a=r(27021),c=r(87085),s=r(85262),u=Object.getPrototypeOf,l=Object.setPrototypeOf,f=Object.prototype,h="__proto__";if(n&&u&&l&&!(h in f))try{o(f,h,{configurable:!0,get:function(){return u(c(this))},set:function(t){var e=s(this);a(t)&&i(e)&&l(e,t)}})}catch(t){}},4199:(t,e,r)=>{r(52798)({target:"Object",stat:!0},{setPrototypeOf:r(64303)})},20276:(t,e,r)=>{r(4359)("asyncIterator")},85029:(t,e,r)=>{var n=r(90047),o=r(4359),i=r(50135);o("toStringTag"),i(n("Symbol"),"Symbol")},27275:(t,e,r)=>{r.d(e,{A:()=>o});r(28251);function n(t,e,r,n,o,i,a){try{var c=t[i](a),s=c.value}catch(t){return void r(t)}c.done?e(s):Promise.resolve(s).then(n,o)}function o(t){return function(){var e=this,r=arguments;return new Promise((function(o,i){var a=t.apply(e,r);function c(t){n(a,o,i,c,s,"next",t)}function s(t){n(a,o,i,c,s,"throw",t)}c(void 0)}))}}},5051:(t,e,r)=>{r.d(e,{A:()=>o});r(45851),r(76863),r(20276),r(51979),r(85029),r(26240),r(43230),r(88405),r(95226),r(30594),r(8134),r(90930),r(25539),r(73015),r(64984),r(94033),r(54531),r(73412),r(4199),r(28251),r(95692),r(59784),r(70549),r(22788),r(5073);var n=r(47748);function o(){o=function(){return e};var t,e={},r=Object.prototype,i=r.hasOwnProperty,a=Object.defineProperty||function(t,e,r){t[e]=r.value},c="function"==typeof Symbol?Symbol:{},s=c.iterator||"@@iterator",u=c.asyncIterator||"@@asyncIterator",l=c.toStringTag||"@@toStringTag";function f(t,e,r){return Object.defineProperty(t,e,{value:r,enumerable:!0,configurable:!0,writable:!0}),t[e]}try{f({},"")}catch(t){f=function(t,e,r){return t[e]=r}}function h(t,e,r,n){var o=e&&e.prototype instanceof w?e:w,i=Object.create(o.prototype),c=new I(n||[]);return a(i,"_invoke",{value:S(t,r,c)}),i}function p(t,e,r){try{return{type:"normal",arg:t.call(e,r)}}catch(t){return{type:"throw",arg:t}}}e.wrap=h;var d="suspendedStart",v="suspendedYield",g="executing",y="completed",m={};function w(){}function x(){}function b(){}var k={};f(k,s,(function(){return this}));var A=Object.getPrototypeOf,L=A&&A(A(P([])));L&&L!==r&&i.call(L,s)&&(k=L);var _=b.prototype=w.prototype=Object.create(k);function O(t){["next","throw","return"].forEach((function(e){f(t,e,(function(t){return this._invoke(e,t)}))}))}function E(t,e){function r(o,a,c,s){var u=p(t[o],t,a);if("throw"!==u.type){var l=u.arg,f=l.value;return f&&"object"==(0,n.A)(f)&&i.call(f,"__await")?e.resolve(f.__await).then((function(t){r("next",t,c,s)}),(function(t){r("throw",t,c,s)})):e.resolve(f).then((function(t){l.value=t,c(l)}),(function(t){return r("throw",t,c,s)}))}s(u.arg)}var o;a(this,"_invoke",{value:function(t,n){function i(){return new e((function(e,o){r(t,n,e,o)}))}return o=o?o.then(i,i):i()}})}function S(e,r,n){var o=d;return function(i,a){if(o===g)throw Error("Generator is already running");if(o===y){if("throw"===i)throw a;return{value:t,done:!0}}for(n.method=i,n.arg=a;;){var c=n.delegate;if(c){var s=z(c,n);if(s){if(s===m)continue;return s}}if("next"===n.method)n.sent=n._sent=n.arg;else if("throw"===n.method){if(o===d)throw o=y,n.arg;n.dispatchException(n.arg)}else"return"===n.method&&n.abrupt("return",n.arg);o=g;var u=p(e,r,n);if("normal"===u.type){if(o=n.done?y:v,u.arg===m)continue;return{value:u.arg,done:n.done}}"throw"===u.type&&(o=y,n.method="throw",n.arg=u.arg)}}}function z(e,r){var n=r.method,o=e.iterator[n];if(o===t)return r.delegate=null,"throw"===n&&e.iterator.return&&(r.method="return",r.arg=t,z(e,r),"throw"===r.method)||"return"!==n&&(r.method="throw",r.arg=new TypeError("The iterator does not provide a '"+n+"' method")),m;var i=p(o,e.iterator,r.arg);if("throw"===i.type)return r.method="throw",r.arg=i.arg,r.delegate=null,m;var a=i.arg;return a?a.done?(r[e.resultName]=a.value,r.next=e.nextLoc,"return"!==r.method&&(r.method="next",r.arg=t),r.delegate=null,m):a:(r.method="throw",r.arg=new TypeError("iterator result is not an object"),r.delegate=null,m)}function j(t){var e={tryLoc:t[0]};1 in t&&(e.catchLoc=t[1]),2 in t&&(e.finallyLoc=t[2],e.afterLoc=t[3]),this.tryEntries.push(e)}function q(t){var e=t.completion||{};e.type="normal",delete e.arg,t.completion=e}function I(t){this.tryEntries=[{tryLoc:"root"}],t.forEach(j,this),this.reset(!0)}function P(e){if(e||""===e){var r=e[s];if(r)return r.call(e);if("function"==typeof e.next)return e;if(!isNaN(e.length)){var o=-1,a=function r(){for(;++o<e.length;)if(i.call(e,o))return r.value=e[o],r.done=!1,r;return r.value=t,r.done=!0,r};return a.next=a}}throw new TypeError((0,n.A)(e)+" is not iterable")}return x.prototype=b,a(_,"constructor",{value:b,configurable:!0}),a(b,"constructor",{value:x,configurable:!0}),x.displayName=f(b,l,"GeneratorFunction"),e.isGeneratorFunction=function(t){var e="function"==typeof t&&t.constructor;return!!e&&(e===x||"GeneratorFunction"===(e.displayName||e.name))},e.mark=function(t){return Object.setPrototypeOf?Object.setPrototypeOf(t,b):(t.__proto__=b,f(t,l,"GeneratorFunction")),t.prototype=Object.create(_),t},e.awrap=function(t){return{__await:t}},O(E.prototype),f(E.prototype,u,(function(){return this})),e.AsyncIterator=E,e.async=function(t,r,n,o,i){void 0===i&&(i=Promise);var a=new E(h(t,r,n,o),i);return e.isGeneratorFunction(r)?a:a.next().then((function(t){return t.done?t.value:a.next()}))},O(_),f(_,l,"Generator"),f(_,s,(function(){return this})),f(_,"toString",(function(){return"[object Generator]"})),e.keys=function(t){var e=Object(t),r=[];for(var n in e)r.push(n);return r.reverse(),function t(){for(;r.length;){var n=r.pop();if(n in e)return t.value=n,t.done=!1,t}return t.done=!0,t}},e.values=P,I.prototype={constructor:I,reset:function(e){if(this.prev=0,this.next=0,this.sent=this._sent=t,this.done=!1,this.delegate=null,this.method="next",this.arg=t,this.tryEntries.forEach(q),!e)for(var r in this)"t"===r.charAt(0)&&i.call(this,r)&&!isNaN(+r.slice(1))&&(this[r]=t)},stop:function(){this.done=!0;var t=this.tryEntries[0].completion;if("throw"===t.type)throw t.arg;return this.rval},dispatchException:function(e){if(this.done)throw e;var r=this;function n(n,o){return c.type="throw",c.arg=e,r.next=n,o&&(r.method="next",r.arg=t),!!o}for(var o=this.tryEntries.length-1;o>=0;--o){var a=this.tryEntries[o],c=a.completion;if("root"===a.tryLoc)return n("end");if(a.tryLoc<=this.prev){var s=i.call(a,"catchLoc"),u=i.call(a,"finallyLoc");if(s&&u){if(this.prev<a.catchLoc)return n(a.catchLoc,!0);if(this.prev<a.finallyLoc)return n(a.finallyLoc)}else if(s){if(this.prev<a.catchLoc)return n(a.catchLoc,!0)}else{if(!u)throw Error("try statement without catch or finally");if(this.prev<a.finallyLoc)return n(a.finallyLoc)}}}},abrupt:function(t,e){for(var r=this.tryEntries.length-1;r>=0;--r){var n=this.tryEntries[r];if(n.tryLoc<=this.prev&&i.call(n,"finallyLoc")&&this.prev<n.finallyLoc){var o=n;break}}o&&("break"===t||"continue"===t)&&o.tryLoc<=e&&e<=o.finallyLoc&&(o=null);var a=o?o.completion:{};return a.type=t,a.arg=e,o?(this.method="next",this.next=o.finallyLoc,m):this.complete(a)},complete:function(t,e){if("throw"===t.type)throw t.arg;return"break"===t.type||"continue"===t.type?this.next=t.arg:"return"===t.type?(this.rval=this.arg=t.arg,this.method="return",this.next="end"):"normal"===t.type&&e&&(this.next=e),m},finish:function(t){for(var e=this.tryEntries.length-1;e>=0;--e){var r=this.tryEntries[e];if(r.finallyLoc===t)return this.complete(r.completion,r.afterLoc),q(r),m}},catch:function(t){for(var e=this.tryEntries.length-1;e>=0;--e){var r=this.tryEntries[e];if(r.tryLoc===t){var n=r.completion;if("throw"===n.type){var o=n.arg;q(r)}return o}}throw Error("illegal catch attempt")},delegateYield:function(e,r,n){return this.delegate={iterator:P(e),resultName:r,nextLoc:n},"next"===this.method&&(this.arg=t),m}},e}}}]);