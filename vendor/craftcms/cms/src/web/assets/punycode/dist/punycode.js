/*! For license information please see punycode.js.LICENSE.txt */
!function(e){var o="object"==typeof exports&&exports&&!exports.nodeType&&exports,n="object"==typeof module&&module&&!module.nodeType&&module,t="object"==typeof global&&global;t.global!==t&&t.window!==t&&t.self!==t||(e=t);var r,u,i=2147483647,f=36,c=/^xn--/,l=/[^\x20-\x7E]/,s=/[\x2E\u3002\uFF0E\uFF61]/g,d={overflow:"Overflow: input needs wider integers to process","not-basic":"Illegal input >= 0x80 (not a basic code point)","invalid-input":"Invalid input"},p=Math.floor,a=String.fromCharCode;function h(e){throw new RangeError(d[e])}function v(e,o){for(var n=e.length,t=[];n--;)t[n]=o(e[n]);return t}function g(e,o){var n=e.split("@"),t="";return n.length>1&&(t=n[0]+"@",e=n[1]),t+v((e=e.replace(s,".")).split("."),o).join(".")}function w(e){for(var o,n,t=[],r=0,u=e.length;r<u;)(o=e.charCodeAt(r++))>=55296&&o<=56319&&r<u?56320==(64512&(n=e.charCodeAt(r++)))?t.push(((1023&o)<<10)+(1023&n)+65536):(t.push(o),r--):t.push(o);return t}function x(e){return v(e,(function(e){var o="";return e>65535&&(o+=a((e-=65536)>>>10&1023|55296),e=56320|1023&e),o+a(e)})).join("")}function b(e,o){return e+22+75*(e<26)-((0!=o)<<5)}function y(e,o,n){var t=0;for(e=n?p(e/700):e>>1,e+=p(e/o);e>455;t+=f)e=p(e/35);return p(t+36*e/(e+38))}function C(e){var o,n,t,r,u,c,l,s,d,a,v,g=[],w=e.length,b=0,C=128,m=72;for((n=e.lastIndexOf("-"))<0&&(n=0),t=0;t<n;++t)e.charCodeAt(t)>=128&&h("not-basic"),g.push(e.charCodeAt(t));for(r=n>0?n+1:0;r<w;){for(u=b,c=1,l=f;r>=w&&h("invalid-input"),((s=(v=e.charCodeAt(r++))-48<10?v-22:v-65<26?v-65:v-97<26?v-97:f)>=f||s>p((i-b)/c))&&h("overflow"),b+=s*c,!(s<(d=l<=m?1:l>=m+26?26:l-m));l+=f)c>p(i/(a=f-d))&&h("overflow"),c*=a;m=y(b-u,o=g.length+1,0==u),p(b/o)>i-C&&h("overflow"),C+=p(b/o),b%=o,g.splice(b++,0,C)}return x(g)}function m(e){var o,n,t,r,u,c,l,s,d,v,g,x,C,m,j,A=[];for(x=(e=w(e)).length,o=128,n=0,u=72,c=0;c<x;++c)(g=e[c])<128&&A.push(a(g));for(t=r=A.length,r&&A.push("-");t<x;){for(l=i,c=0;c<x;++c)(g=e[c])>=o&&g<l&&(l=g);for(l-o>p((i-n)/(C=t+1))&&h("overflow"),n+=(l-o)*C,o=l,c=0;c<x;++c)if((g=e[c])<o&&++n>i&&h("overflow"),g==o){for(s=n,d=f;!(s<(v=d<=u?1:d>=u+26?26:d-u));d+=f)j=s-v,m=f-v,A.push(a(b(v+j%m,0))),s=p(j/m);A.push(a(b(s,0))),u=y(n,C,t==r),n=0,++t}++n,++o}return A.join("")}if(r={version:"1.4.1",ucs2:{decode:w,encode:x},decode:C,encode:m,toASCII:function(e){return g(e,(function(e){return l.test(e)?"xn--"+m(e):e}))},toUnicode:function(e){return g(e,(function(e){return c.test(e)?C(e.slice(4).toLowerCase()):e}))}},"function"==typeof define&&"object"==typeof define.amd&&define.amd)define("punycode",(function(){return r}));else if(o&&n)if(module.exports==o)n.exports=r;else for(u in r)r.hasOwnProperty(u)&&(o[u]=r[u]);else e.punycode=r}(this);