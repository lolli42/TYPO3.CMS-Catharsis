!function(a){"object"==typeof exports&&"object"==typeof module?a(require("../../lib/codemirror")):"function"==typeof define&&define.amd?define(["../../lib/codemirror"],a):a(CodeMirror)}(function(a){"use strict";a.defineMode("rpm-changes",function(){var a=/^-+$/,b=/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)  ?\d{1,2} \d{2}:\d{2}(:\d{2})? [A-Z]{3,4} \d{4} - /,c=/^[\w+.-]+@[\w.-]+/;return{token:function(d){if(d.sol()){if(d.match(a))return"tag";if(d.match(b))return"tag"}return d.match(c)?"string":(d.next(),null)}}}),a.defineMIME("text/x-rpm-changes","rpm-changes"),a.defineMode("rpm-spec",function(){var a=/^(i386|i586|i686|x86_64|ppc64le|ppc64|ppc|ia64|s390x|s390|sparc64|sparcv9|sparc|noarch|alphaev6|alpha|hppa|mipsel)/,b=/^[a-zA-Z0-9()]+:/,c=/^%(debug_package|package|description|prep|build|install|files|clean|changelog|preinstall|preun|postinstall|postun|pretrans|posttrans|pre|post|triggerin|triggerun|verifyscript|check|triggerpostun|triggerprein|trigger)/,d=/^%(ifnarch|ifarch|if)/,e=/^%(else|endif)/,f=/^(\!|\?|\<\=|\<|\>\=|\>|\=\=|\&\&|\|\|)/;return{startState:function(){return{controlFlow:!1,macroParameters:!1,section:!1}},token:function(g,h){var i=g.peek();if("#"==i)return g.skipToEnd(),"comment";if(g.sol()){if(g.match(b))return"header";if(g.match(c))return"atom"}if(g.match(/^\$\w+/))return"def";if(g.match(/^\$\{\w+\}/))return"def";if(g.match(e))return"keyword";if(g.match(d))return h.controlFlow=!0,"keyword";if(h.controlFlow){if(g.match(f))return"operator";if(g.match(/^(\d+)/))return"number";g.eol()&&(h.controlFlow=!1)}if(g.match(a))return g.eol()&&(h.controlFlow=!1),"number";if(g.match(/^%[\w]+/))return g.match(/^\(/)&&(h.macroParameters=!0),"keyword";if(h.macroParameters){if(g.match(/^\d+/))return"number";if(g.match(/^\)/))return h.macroParameters=!1,"keyword"}return g.match(/^%\{\??[\w \-\:\!]+\}/)?(g.eol()&&(h.controlFlow=!1),"def"):(g.next(),null)}}}),a.defineMIME("text/x-rpm-spec","rpm-spec")});