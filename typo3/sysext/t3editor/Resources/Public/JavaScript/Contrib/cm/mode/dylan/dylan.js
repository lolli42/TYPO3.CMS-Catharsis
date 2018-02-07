!function(a){"object"==typeof exports&&"object"==typeof module?a(require("../../lib/codemirror")):"function"==typeof define&&define.amd?define(["../../lib/codemirror"],a):a(CodeMirror)}(function(a){"use strict";function b(a,b){for(var c=0;c<a.length;c++)b(a[c],c)}function c(a,b){for(var c=0;c<a.length;c++)if(b(a[c],c))return!0;return!1}a.defineMode("dylan",function(a){function d(a,b,c){return b.tokenize=c,c(a,b)}function e(a,b){var e=a.peek();if("'"==e||'"'==e)return a.next(),d(a,b,g(e,"string"));if("/"==e){if(a.next(),a.eat("*"))return d(a,b,f);if(a.eat("/"))return a.skipToEnd(),"comment";a.backUp(1)}else if(/[+\-\d\.]/.test(e)){if(a.match(/^[+-]?[0-9]*\.[0-9]*([esdx][+-]?[0-9]+)?/i)||a.match(/^[+-]?[0-9]+([esdx][+-]?[0-9]+)/i)||a.match(/^[+-]?\d+/))return"number"}else{if("#"==e)return a.next(),e=a.peek(),'"'==e?(a.next(),d(a,b,g('"',"string"))):"b"==e?(a.next(),a.eatWhile(/[01]/),"number"):"x"==e?(a.next(),a.eatWhile(/[\da-f]/i),"number"):"o"==e?(a.next(),a.eatWhile(/[0-7]/),"number"):"#"==e?(a.next(),"punctuation"):"["==e||"("==e?(a.next(),"bracket"):a.match(/f|t|all-keys|include|key|next|rest/i)?"atom":(a.eatWhile(/[-a-zA-Z]/),"error");if("~"==e)return a.next(),e=a.peek(),"="==e?(a.next(),e=a.peek(),"="==e?(a.next(),"operator"):"operator"):"operator";if(":"==e){if(a.next(),e=a.peek(),"="==e)return a.next(),"operator";if(":"==e)return a.next(),"punctuation"}else{if("[](){}".indexOf(e)!=-1)return a.next(),"bracket";if(".,".indexOf(e)!=-1)return a.next(),"punctuation";if(a.match("end"))return"keyword"}}for(var h in k)if(k.hasOwnProperty(h)){var i=k[h];if(i instanceof Array&&c(i,function(b){return a.match(b)})||a.match(i))return l[h]}return/[+\-*\/^=<>&|]/.test(e)?(a.next(),"operator"):a.match("define")?"def":(a.eatWhile(/[\w\-]/),o.hasOwnProperty(a.current())?p[a.current()]:a.current().match(j)?"variable":(a.next(),"variable-2"))}function f(a,b){for(var c,d=!1,f=!1,g=0;c=a.next();){if("/"==c&&d){if(!(g>0)){b.tokenize=e;break}g--}else"*"==c&&f&&g++;d="*"==c,f="/"==c}return"comment"}function g(a,b){return function(c,d){for(var f,g=!1,h=!1;null!=(f=c.next());){if(f==a&&!g){h=!0;break}g=!g&&"\\"==f}return!h&&g||(d.tokenize=e),b}}var h={unnamedDefinition:["interface"],namedDefinition:["module","library","macro","C-struct","C-union","C-function","C-callable-wrapper"],typeParameterizedDefinition:["class","C-subtype","C-mapped-subtype"],otherParameterizedDefinition:["method","function","C-variable","C-address"],constantSimpleDefinition:["constant"],variableSimpleDefinition:["variable"],otherSimpleDefinition:["generic","domain","C-pointer-type","table"],statement:["if","block","begin","method","case","for","select","when","unless","until","while","iterate","profiling","dynamic-bind"],separator:["finally","exception","cleanup","else","elseif","afterwards"],other:["above","below","by","from","handler","in","instance","let","local","otherwise","slot","subclass","then","to","keyed-by","virtual"],signalingCalls:["signal","error","cerror","break","check-type","abort"]};h.otherDefinition=h.unnamedDefinition.concat(h.namedDefinition).concat(h.otherParameterizedDefinition),h.definition=h.typeParameterizedDefinition.concat(h.otherDefinition),h.parameterizedDefinition=h.typeParameterizedDefinition.concat(h.otherParameterizedDefinition),h.simpleDefinition=h.constantSimpleDefinition.concat(h.variableSimpleDefinition).concat(h.otherSimpleDefinition),h.keyword=h.statement.concat(h.separator).concat(h.other);var i="[-_a-zA-Z?!*@<>$%]+",j=new RegExp("^"+i),k={symbolKeyword:i+":",symbolClass:"<"+i+">",symbolGlobal:"\\*"+i+"\\*",symbolConstant:"\\$"+i},l={symbolKeyword:"atom",symbolClass:"tag",symbolGlobal:"variable-2",symbolConstant:"variable-3"};for(var m in k)k.hasOwnProperty(m)&&(k[m]=new RegExp("^"+k[m]));k.keyword=[/^with(?:out)?-[-_a-zA-Z?!*@<>$%]+/];var n={};n.keyword="keyword",n.definition="def",n.simpleDefinition="def",n.signalingCalls="builtin";var o={},p={};return b(["keyword","definition","simpleDefinition","signalingCalls"],function(a){b(h[a],function(b){o[b]=a,p[b]=n[a]})}),{startState:function(){return{tokenize:e,currentIndent:0}},token:function(a,b){if(a.eatSpace())return null;var c=b.tokenize(a,b);return c},blockCommentStart:"/*",blockCommentEnd:"*/"}}),a.defineMIME("text/x-dylan","dylan")});