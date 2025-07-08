import{B as c,q as u,m as a,e as r,h as o,f as d,n as t,x as n,d as $,c as m,w as l,k as f,j as b}from"./index-DAS6CEhR.js";var y=({dt:e})=>`
.p-card {
    background: ${e("card.background")};
    color: ${e("card.color")};
    box-shadow: ${e("card.shadow")};
    border-radius: ${e("card.border.radius")};
    display: flex;
    flex-direction: column;
}

.p-card-caption {
    display: flex;
    flex-direction: column;
    gap: ${e("card.caption.gap")};
}

.p-card-body {
    padding: ${e("card.body.padding")};
    display: flex;
    flex-direction: column;
    gap: ${e("card.body.gap")};
}

.p-card-title {
    font-size: ${e("card.title.font.size")};
    font-weight: ${e("card.title.font.weight")};
}

.p-card-subtitle {
    color: ${e("card.subtitle.color")};
}
`,v={root:"p-card p-component",header:"p-card-header",body:"p-card-body",caption:"p-card-caption",title:"p-card-title",subtitle:"p-card-subtitle",content:"p-card-content",footer:"p-card-footer"},h=c.extend({name:"card",style:y,classes:v}),g={name:"BaseCard",extends:u,style:h,provide:function(){return{$pcCard:this,$parentInstance:this}}},i={name:"Card",extends:g,inheritAttrs:!1};function k(e,p,s,w,B,C){return r(),a("div",t({class:e.cx("root")},e.ptmi("root")),[e.$slots.header?(r(),a("div",t({key:0,class:e.cx("header")},e.ptm("header")),[n(e.$slots,"header")],16)):o("",!0),d("div",t({class:e.cx("body")},e.ptm("body")),[e.$slots.title||e.$slots.subtitle?(r(),a("div",t({key:0,class:e.cx("caption")},e.ptm("caption")),[e.$slots.title?(r(),a("div",t({key:0,class:e.cx("title")},e.ptm("title")),[n(e.$slots,"title")],16)):o("",!0),e.$slots.subtitle?(r(),a("div",t({key:1,class:e.cx("subtitle")},e.ptm("subtitle")),[n(e.$slots,"subtitle")],16)):o("",!0)],16)):o("",!0),d("div",t({class:e.cx("content")},e.ptm("content")),[n(e.$slots,"content")],16),e.$slots.footer?(r(),a("div",t({key:1,class:e.cx("footer")},e.ptm("footer")),[n(e.$slots,"footer")],16)):o("",!0)],16)],16)}i.render=k;const S=$({__name:"Info",setup(e){return(p,s)=>(r(),m(b(i),{style:{"max-width":"50rem"}},{title:l(()=>s[0]||(s[0]=[f("Автоматизируйте рутинные действия в масштабах вашего портала")])),content:l(()=>s[1]||(s[1]=[d("p",{class:"m-0"},' Во вкладке "Задачи" вы можете посмотреть список текущих задач(не путать с задачами в Битрикс - далее события) и добавлять новые события. События вкладки задач позволяют в определённое время изменять статус задач у выбранных сотрудников. События могут быть приостановлены или удалены. Вкладка "Учёт рабочего времени" напоминает вкладку "Задачи", имеет похожую логику, но через неё вы оперируете над рабочим временем сотрудников - начинаете, приостанавливаете, возобнавляете и завершаете рабочие дни сотрудников. ',-1)])),_:1}))}});export{S as default};
