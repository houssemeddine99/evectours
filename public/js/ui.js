/* Travagir UI Enhancements */
(function(){
  /* 1. Toast */
  var rack=document.getElementById('tg-toast-rack');
  var ICONS={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
  var DUR={success:4000,error:6000,warning:5000,info:4000};
  window.tgToast=function(msg,type,dur){
    type=type||'info';dur=dur||DUR[type]||4000;
    var t=document.createElement('div');
    t.className='tg-toast '+type;t.style.setProperty('--dur',dur+'ms');
    t.innerHTML='<span class="tg-toast-icon">'+(ICONS[type]||'ℹ️')+'</span><span class="tg-toast-msg">'+msg+'</span><button class="tg-toast-close" aria-label="Close">✕</button>';
    rack.appendChild(t);
    t.querySelector('.tg-toast-close').onclick=function(){dismiss(t);};
    setTimeout(function(){dismiss(t);},dur);
  };
  function dismiss(t){if(t.classList.contains('out'))return;t.classList.add('out');t.addEventListener('animationend',function(){t.remove();},{once:true});}
  document.querySelectorAll('.tg-flash-data').forEach(function(el){var type=el.dataset.type;if(type==='danger')type='error';tgToast(el.dataset.msg,type);el.remove();});

  /* 2. Back-to-top */
  var topBtn=document.getElementById('tg-top');
  if(topBtn){
    window.addEventListener('scroll',function(){topBtn.classList.toggle('visible',window.scrollY>320);},{passive:true});
    topBtn.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'});});
  }

  /* 3. Page transition */
  document.addEventListener('click',function(e){
    var a=e.target.closest('a');if(!a)return;
    var href=a.getAttribute('href');
    if(!href||href.startsWith('#')||href.startsWith('javascript')||a.target==='_blank'||e.ctrlKey||e.metaKey)return;
    try{var u=new URL(href,location.href);if(u.origin!==location.origin)return;}catch(x){return;}
    e.preventDefault();document.body.classList.add('tg-leaving');
    setTimeout(function(){location.href=href;},200);
  });

  /* 4. Image blur-up */
  function initBlur(img){
    img.classList.add('loading');
    var io=new IntersectionObserver(function(entries,ob){
      entries.forEach(function(entry){
        if(!entry.isIntersecting)return;
        var i=entry.target,src=i.dataset.src||i.src,tmp=new Image();
        tmp.onload=function(){i.src=src;i.classList.remove('loading');i.classList.add('loaded');};
        tmp.src=src;ob.unobserve(i);
      });
    },{rootMargin:'120px'});
    io.observe(img);
  }
  document.querySelectorAll('.tg-img-wrap img').forEach(initBlur);

  /* 5. Confirm dialog */
  var overlay=document.getElementById('tg-confirm-overlay');
  if(overlay){
    var titleEl=document.getElementById('tg-confirm-title');
    var msgEl=document.getElementById('tg-confirm-msg');
    var okBtn=document.getElementById('tg-confirm-ok');
    var cancelBtn=document.getElementById('tg-confirm-cancel');
    var _resolve;
    window.tgConfirm=function(opts){
      opts=opts||{};
      titleEl.textContent=opts.title||'Are you sure?';msgEl.textContent=opts.msg||'';
      okBtn.textContent=opts.ok||'Confirm';okBtn.className='tg-confirm-ok'+(opts.safe?' safe':'');
      document.getElementById('tg-confirm-icon').textContent=opts.icon||(opts.safe?'✅':'⚠️');
      overlay.classList.add('open');
      return new Promise(function(res){_resolve=res;});
    };
    function closeDialog(val){overlay.classList.remove('open');if(_resolve)_resolve(val);}
    okBtn.addEventListener('click',function(){closeDialog(true);});
    cancelBtn.addEventListener('click',function(){closeDialog(false);});
    overlay.addEventListener('click',function(e){if(e.target===overlay)closeDialog(false);});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&overlay.classList.contains('open'))closeDialog(false);});
    document.querySelectorAll('[data-confirm]').forEach(function(el){
      el.addEventListener('click',async function(e){
        e.preventDefault();
        var ok=await tgConfirm({title:el.dataset.confirmTitle||'Are you sure?',msg:el.dataset.confirm||'This action cannot be undone.'});
        if(ok){var form=el.closest('form');if(form)form.submit();else if(el.href)location.href=el.href;}
      });
    });
  }

  /* 6. Form autosave */
  document.querySelectorAll('form[data-autosave]').forEach(function(form){
    var key='tg_autosave_'+(form.dataset.autosave||form.id||form.action);
    var indicator=form.querySelector('.tg-autosave'),timer;
    try{var saved=JSON.parse(localStorage.getItem(key));if(saved)Object.entries(saved).forEach(function([n,v]){var el=form.elements[n];if(el&&el.type!=='file'&&el.type!=='hidden')el.value=v;});}catch(x){}
    form.addEventListener('input',function(){
      clearTimeout(timer);timer=setTimeout(function(){
        var data={};Array.from(form.elements).forEach(function(el){if(el.name&&el.type!=='file'&&el.type!=='hidden'&&el.type!=='submit')data[el.name]=el.value;});
        try{localStorage.setItem(key,JSON.stringify(data));}catch(x){}
        if(indicator){indicator.classList.add('visible');setTimeout(function(){indicator.classList.remove('visible');},2000);}
      },600);
    });
    form.addEventListener('submit',function(){try{localStorage.removeItem(key);}catch(x){}});
  });

  /* 7. Recently viewed */
  var MAX=6,KEY='tg_recent_voyages';
  window.tgTrackVoyage=function(id,title,img,url){
    try{var list=JSON.parse(localStorage.getItem(KEY))||[];list=list.filter(function(v){return v.id!==id;});list.unshift({id:id,title:title,img:img,url:url});list=list.slice(0,MAX);localStorage.setItem(KEY,JSON.stringify(list));}catch(x){}
  };
  window.tgRenderRecent=function(sel){
    try{var list=JSON.parse(localStorage.getItem(KEY))||[];if(!list.length)return;var c=document.querySelector(sel);if(!c)return;var s=document.createElement('div');s.className='tg-recent-section';s.innerHTML='<h3>Recently Viewed</h3><div class="tg-recent-row">'+list.map(function(v){return'<a class="tg-recent-chip" href="'+v.url+'"><img class="tg-recent-thumb" src="'+v.img+'" alt="" loading="lazy"><span class="tg-recent-label">'+v.title+'</span></a>';}).join('')+'</div>';c.appendChild(s);}catch(x){}
  };

  /* 8. Countdowns */
  document.querySelectorAll('.tg-countdown[data-ends]').forEach(function(el){
    function tick(){var diff=new Date(el.dataset.ends).getTime()-Date.now();if(diff<=0){el.textContent='Expired';el.classList.add('urgent');return;}var d=Math.floor(diff/86400000),h=Math.floor((diff%86400000)/3600000),m=Math.floor((diff%3600000)/60000),s=Math.floor((diff%60000)/1000);var val=el.querySelector('.tg-countdown-val');if(val)val.textContent=(d?d+'d ':'')+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');el.classList.toggle('urgent',diff<3600000);}
    tick();setInterval(tick,1000);
  });

  /* 9. Share */
  window.tgShare=async function(title,url){
    url=url||location.href;
    if(navigator.share){try{await navigator.share({title:title,url:url});return;}catch(x){}}
    try{await navigator.clipboard.writeText(url);tgToast('Link copied!','success');}catch(x){tgToast('Copy failed','error');}
  };

  /* 10. Submit loading states */
  document.querySelectorAll('form').forEach(function(form){
    form.addEventListener('submit',function(){
      form.querySelectorAll('[type="submit"]').forEach(function(btn){
        if(btn.dataset.noLoadingState!==undefined)return;
        btn.disabled=true;
        btn.innerHTML='<span style="display:inline-flex;align-items:center;gap:6px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:tg-spin .7s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>Please wait…</span>';
      });
    });
  });
})();
