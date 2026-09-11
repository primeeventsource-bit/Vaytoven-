import { escapeHtml as e, dollarsToCents, safeUrl, formatMoney as money } from './utils.js';

const main = document.querySelector('#main');
const dialog = document.querySelector('#dialog');
const paths = {
  search:'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
  heart:'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
  offers:'<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h4"/>',
  help:'<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-1 2V11.5a9 9 0 0 1 18 0Z"/><path d="M8 11h.01M12 11h.01M16 11h.01"/>',
  user:'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
  arrow:'<path d="M5 12h14m-6-6 6 6-6 6"/>',
  back:'<path d="M19 12H5m6-6-6 6 6 6"/>',
  pin:'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
  filter:'<path d="M4 7h16M4 17h16"/><circle cx="8" cy="7" r="2" fill="currentColor"/><circle cx="16" cy="17" r="2" fill="currentColor"/>',
  globe:'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
  close:'<path d="m6 6 12 12M18 6 6 18"/>',
  share:'<path d="M12 16V3m-4 4 4-4 4 4M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/>',
  send:'<path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/>',
  lock:'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/>',
  book:'<path d="M12 5C8 2 4 3 2 4v16c3-2 6-2 10 0 4-2 7-2 10 0V4c-3-1-6-2-10 1Zm0 0v15"/>',
  home:'<path d="m3 10 9-7 9 7v11h-7v-7h-4v7H3Z"/>',
  sun:'<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1"/>',
};
const icon = name => `<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">${paths[name] || paths.arrow}</svg>`;
const native = window.Capacitor?.isNativePlatform?.() || false;
const platform = window.Capacitor?.getPlatform?.() || 'web';
const base = window.VAYTOVEN_CONFIG?.apiUrl || (native ? '' : location.origin);
const plugin = name => window.Capacitor?.registerPlugin?.(name);
const state = { token:null, user:null, terms:[], saved:[], results:[], meta:null, query:'', capacity:'', price:'', route:0, property:null, photo:0, chat:[], chatId:null, visitor:crypto.randomUUID(), offerPage:1 };
const tabs = [['explore','search','Explore'],['saved','heart','Saved'],['offers','offers','Offers'],['support','help','Support'],['account','user','Account']];
let toastTimer;

function toast(message) {
  const el = document.querySelector('#toast');
  el.textContent = message; el.hidden = false; clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.hidden = true; }, 4000);
}
function loading() { return '<div class="loading" role="status">Just a moment…</div>'; }
function empty(symbol,title,text,action='') { return `<section class="empty"><div class="empty-symbol">${icon(symbol)}</div><h2>${e(title)}</h2><p>${e(text)}</p>${action}</section>`; }
function heading(title,text) { return `<div class="page-title"><h1>${e(title)}</h1><p>${e(text)}</p></div>`; }
function errorBlock(error) { return empty('globe','Let’s try that again', error.message, '<button class="primary" data-action="retry">Try again</button>'); }
function resetAccount() { state.token = null; state.user = null; state.saved = []; state.terms = []; state.chat=[]; state.chatId=null; state.visitor=crypto.randomUUID(); document.querySelector('#avatar').textContent='V'; }
async function api(path, options={}) {
  if (!base) throw new Error('This app’s server address has not been configured.');
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 20000);
  try {
    const response = await fetch(`${base}/api/v1/${path}`, {method:options.method || 'GET',credentials:'omit',cache:'no-store',signal:controller.signal,headers:{'Accept':'application/json','Content-Type':'application/json','X-Vaytoven-Surface':platform==='ios'?'app_ios':platform==='android'?'app_android':'web',...(state.token?{Authorization:`Bearer ${state.token}`}:{})},...(options.body?{body:JSON.stringify(options.body)}:{})});
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status===401 && state.token) resetAccount();
      if ([409,423].includes(response.status) && state.token && !path.startsWith('mobile/account')) location.hash='account';
      const error = new Error(data.reply || (data.errors ? Object.values(data.errors).flat().join(' ') : data.message) || 'We couldn’t complete that request. Please try again.');
      error.status=response.status; error.data=data; throw error;
    }
    return data;
  } catch(error) {
    if (error.name==='AbortError') throw new Error('The connection took too long. Please try again.');
    if (error instanceof TypeError) throw new Error('Unable to reach Vaytoven. Check your connection and try again.');
    throw error;
  } finally { clearTimeout(timeout); }
}
async function loadAccount() {
  if (!state.token) return;
  const data=await api('mobile/account'); state.user=data.user;state.terms=data.required_terms;
  document.querySelector('#avatar').textContent=state.user.name.slice(0,1).toUpperCase();
  if (!state.user.must_change_password && state.terms.every(t=>t.accepted)) state.saved=(await api('mobile/saved')).data;
}
function requiresLogin(title='Your next chapter starts here') {
  return empty('user',title,'Sign in to save your favorite places, send offers, and keep everything together.', '<a href="#account" class="primary">Sign in or create an account</a>');
}
function propertyCard(p) {
  const saved=state.saved.some(s=>s.id===p.id);
  const photo=safeUrl(p.photos?.[0]?.url || 'brand.svg', base || location.href);
  return `<article class="property-card"><button class="save-button ${saved?'saved':''}" data-action="save" data-id="${p.id}" aria-label="${saved?'Unsave':'Save'} ${e(p.title)}" aria-pressed="${saved}">${icon('heart')}</button><a href="#property/${p.id}"><div class="property-image"><img src="${e(photo)}" alt="${e(p.title)}" loading="lazy"><span class="type">${p.listing_type==='sale'?'For sale':'Vacation property'}</span></div><div class="property-location">${e(p.location.city)} · ${e(p.location.country)}</div><h3>${e(p.title)}</h3><p class="property-meta">${p.capacity} guests · ${p.bedrooms} bedroom${p.bedrooms===1?'':'s'}</p><div class="property-price">${money(p.price_cents)} <span>${e(p.price_caption||'Asking price')}</span>${icon('arrow')}</div></a></article>`;
}
function searchForm(){return `<form id="search-form" class="search-box">${icon('search')}<input type="search" name="q" aria-label="Search destination or property" placeholder="Where would you love to go?" value="${e(state.query)}" maxlength="128"><button aria-label="Search properties">${icon('arrow')}</button></form>`;}
function chips(){return `<div class="destinations" aria-label="Destinations">${[['','globe','Anywhere'],['Orlando','sun','Orlando'],['Bali','sun','Bali'],['Santorini','sun','Santorini'],['Lake Tahoe','home','Lake Tahoe'],['Paris','pin','Paris'],['Tokyo','pin','Tokyo']].map(([q,i,t])=>`<button class="chip ${state.query===q?'active':''}" data-action="destination" data-query="${e(q)}">${icon(i)}${t}</button>`).join('')}</div>`;}
async function explore(id) {
  const isSearch=state.query||state.capacity||state.price;
  main.innerHTML=`<div class="page">${!isSearch?`<section class="hero"><div class="hero-copy"><div class="eyebrow">Good places. Great possibilities.</div><h1>Find your place<br><span class="gradient-text">anywhere.</span></h1><p>A slower morning. A different view.<br>Discover vacation homes worth getting away for.</p><a class="text-button" href="#results" data-action="scroll-results">Explore the collection ${icon('arrow')}</a></div><a href="#destination/Bali" class="hero-image"><img src="https://images.unsplash.com/photo-1540541338287-41700207dee6?w=1000&q=85" alt="Palm-lined resort pool in Bali"><div class="image-note">${icon('pin')}<div><strong>A little piece of paradise.</strong>Bali, Indonesia</div></div></a></section>`:heading(state.query?`Somewhere in ${state.query}`:'Find your kind of getaway','Explore advertised vacation properties and connect directly with owners.')}${searchForm()}${chips()}<div class="section-head"><div><h2>${isSearch?'Your next escape':'Places to fall for'}</h2><p>${isSearch?'A place for every kind of getaway.':'A little inspiration for your next chapter.'}</p></div><button class="text-button" data-action="filters">${icon('filter')} Filters${state.capacity||state.price?' •':''}</button></div><div id="results">${loading()}</div><a class="promo" data-web="/list-your-property" href="${e(base)}/list-your-property"><div><div class="eyebrow">For property owners</div><h2>Your place. Their next escape.</h2><p>Give your vacation property a world of possibilities.</p></div><span class="round-arrow">${icon('arrow')}</span></a><p class="footnote">A place to discover. A way to connect.<br>Owners and guests agree on arrangements directly.</p></div>`;
  await fetchProperties(id,1);
}
async function fetchProperties(id,page) {
  const params=new URLSearchParams({per_page:'12',page:String(page)});
  if(state.query)params.set('q',state.query);
  if(state.capacity)params.set('min_capacity',state.capacity);
  if(state.price)params.set('max_price_cents',state.price);
  try {
    const data=await api(`properties?${params}`); if(id!==state.route)return;
    state.results=page===1?data.data:[...state.results,...data.data];state.meta=data.meta;
    document.querySelector('#results').innerHTML=state.results.length?`<div class="results-tools"><p>${data.meta.total} advertised properties</p>${state.query||state.capacity||state.price?'<button class="text-button" data-action="clear-filters">Clear filters</button>':''}</div><div class="property-grid">${state.results.map(propertyCard).join('')}</div>${data.meta.current_page<data.meta.last_page?'<button class="secondary load-more" data-action="more">Discover more</button>':''}`:empty('search','A new destination awaits','No properties match these filters. Try another destination or widen your search.','<button class="primary" data-action="clear-filters">Explore all properties</button>');
  } catch(error){if(id===state.route)document.querySelector('#results').innerHTML=errorBlock(error);}
}
async function detail(id,propertyId) {
  const p=(await api(`properties/${propertyId}`)).data; if(id!==state.route)return;state.property=p;state.photo=0;
  const photos=p.photos||[];
  main.innerHTML=`<div class="page"><div class="detail-header"><a href="#explore" class="icon-button" aria-label="Back to explore">${icon('back')}</a><div class="actions"><button class="icon-button" data-action="share" aria-label="Share property">${icon('share')}</button><button class="icon-button" data-action="save" data-id="${p.id}" aria-label="Save property">${icon('heart')}</button></div></div><div class="detail-gallery"><img id="detail-photo" src="${e(safeUrl(photos[0]?.url||'brand.svg',base||location.href))}" alt="${e(p.title)}">${photos.length>1?`<div class="gallery-controls"><button data-action="photo-prev" aria-label="Previous photo">${icon('back')}</button><span id="photo-count">1 / ${photos.length}</span><button data-action="photo-next" aria-label="Next photo">${icon('arrow')}</button></div>`:''}</div><div class="detail-content"><section><div class="eyebrow">${e(p.location.city)} · ${e(p.location.country)}</div><h1>${e(p.title)}</h1><p class="muted">Property ${e(p.reference)}</p><div class="facts"><span>${p.capacity} guests</span><span>${p.bedrooms} bedrooms</span><span>${p.bathrooms} bathrooms</span></div><div class="divider"></div><h2>A place to make your own</h2><p>${e(p.description)}</p><h2>The little extras</h2><div class="amenities">${p.amenities?.map(a=>`<span>${e(a.label)}</span>`).join('')||'<p>Ask the owner for more information.</p>'}</div></section><aside class="offer-box"><div class="price">${money(p.price_cents)}</div><small>${e(p.price_caption||'Advertised asking price')} · USD</small><button class="primary full" data-action="offer">Send an offer ${icon('arrow')}</button><button class="text-button full" data-action="inquire">Ask the owner a question</button><p>Send your dates and a message to the owner. Arrangements and payments are agreed directly. Submitting an offer does not reserve the property.</p></aside></div></div>`;
}
async function saved(id) {
  if(!state.user){main.innerHTML=`<div class="page">${heading('Some places stay with you.','Keep your favorites close.')}${requiresLogin('Your own little collection')}</div>`;return;}
  await loadAccount();if(id!==state.route)return;
  if(accountGate())return;
  main.innerHTML=`<div class="page">${heading('Your saved places','A collection of possibilities, ready when you are.')}${state.saved.length?`<div class="property-grid">${state.saved.map(propertyCard).join('')}</div>`:empty('heart','Start a little collection','Tap the heart on any property to save it here. Your saved places stay with your account.','<a class="primary" href="#explore">Find your next escape</a>')}</div>`;
}
async function offers(id,page=1) {
  if(!state.user){main.innerHTML=`<div class="page">${heading('Keep the conversation going.','Your offers and inquiries, all in one place.')}${requiresLogin('Make your next move')}</div>`;return;}
  await loadAccount();if(id!==state.route)return;if(accountGate())return;
  const data=await api(`mobile/offers?page=${page}`);if(id!==state.route)return;state.offerPage=page;
  main.innerHTML=`<div class="page">${heading('Your offers & inquiries','Connect directly. Make a plan. Find your place.')}${data.data.length?data.data.map(o=>`<article class="offer-card"><div class="offer-head"><span>${e(o.reference)} · ${o.is_received?'Received':'Sent'}</span><span class="badge ${e(o.status)}">${e(o.status)}</span></div><a href="#property/${o.property?.id}"><h3>${e(o.property?.title||'Property no longer available')}</h3></a><p>${e(o.property?.city)}</p><p class="amount">${o.kind==='offer'?money(o.amount_cents):'Property inquiry'}</p>${o.check_in?`<p>${e(o.check_in)} → ${e(o.check_out)}${o.guests?` · ${o.guests} guests`:''}</p>`:''}<p>${e(o.message||'No message included.')}</p>${o.owner_response?`<p><strong>Owner’s reply:</strong> ${e(o.owner_response)}</p>`:''}${o.can_respond?`<div class="actions"><button class="primary" data-action="respond" data-id="${o.id}" data-decision="accepted">Accept</button><button class="secondary" data-action="respond" data-id="${o.id}" data-decision="declined">Decline</button></div>`:''}</article>`).join(''):empty('offers','Every getaway starts somewhere','Send an offer or a question from a property page. You can follow its response here.','<a href="#explore" class="primary">Explore properties</a>')}${data.meta.last_page>1?`<div class="actions">${page>1?'<button class="secondary" data-action="offers-prev">Previous</button>':''}<span>Page ${page} of ${data.meta.last_page}</span>${page<data.meta.last_page?'<button class="secondary" data-action="offers-next">Next</button>':''}</div>`:''}</div>`;
}
function accountGate() {
  if(state.user?.must_change_password){location.hash='account';return true;}
  if(state.terms.some(t=>!t.accepted)){location.hash='account';return true;}
  return false;
}
function authForm(register=false) {
  return `<div class="account-panel"><div class="eyebrow">Your world, a little wider</div><h1>${register?'Make room for<br>new possibilities.':'Welcome<br>back, explorer.'}</h1><p class="muted">${register?'Create an account and find your next favorite place.':'Your favorite places and next adventures are waiting.'}</p><form id="auth-form" class="form-stack" data-register="${register}">${register?'<div><label for="name">Your name</label><input id="name" name="name" autocomplete="name" required maxlength="255"></div>':''}<div><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="255"></div><div><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="${register?'new-password':'current-password'}" minlength="${register?10:1}" required></div>${register?'<div><label for="confirmation">Confirm password</label><input id="confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></div><p class="form-hint">You’ll review the current terms before saving properties or sending offers.</p>':''}<div class="form-error" role="alert"></div><button class="primary full">${register?'Create your account':'Sign in'} ${icon('arrow')}</button></form>${!register?`<a class="text-button" href="${e(base)}/forgot-password" data-web="/forgot-password">Forgot your password?</a>`:''}<div class="divider"></div><p class="form-hint">${register?'Already part of Vaytoven?':'New around here?'} <button class="text-button" data-action="auth-switch" data-register="${!register}">${register?'Sign in':'Create an account'}</button></p><p class="footnote">Your sign-in stays in memory for this app session.<br>Your password is never saved on this device.</p></div>`;
}
function passwordForm() {
  return `<div class="account-panel"><div class="eyebrow">A fresh start</div><h1>Make this account yours.</h1><p class="muted">Choose a new password before continuing.</p><form id="password-form" class="form-stack"><div><label for="current">Current password</label><input id="current" name="current_password" type="password" autocomplete="current-password" required></div><div><label for="new-password">New password</label><input id="new-password" name="password" type="password" autocomplete="new-password" required minlength="10"></div><div><label for="confirm-password">Confirm new password</label><input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required minlength="10"></div><p class="form-hint">At least 10 characters, including letters and numbers. Other app sessions will be signed out.</p><div class="form-error" role="alert"></div><button class="primary">Update password</button></form><button class="text-button" data-action="logout">Sign out</button></div>`;
}
async function account(id) {
  if(!state.token){main.innerHTML=`<div class="page">${authForm()}</div>`;return;}
  await loadAccount();if(id!==state.route)return;
  if(state.user.must_change_password){main.innerHTML=`<div class="page">${passwordForm()}</div>`;return;}
  if(state.terms.some(t=>!t.accepted)){
    main.innerHTML=`<div class="page"><div class="account-panel"><div class="eyebrow">Before your next chapter</div><h1>A moment to<br>get acquainted.</h1><p class="muted">Review these documents before using your account.</p><form id="terms-form" class="form-stack">${state.terms.map(t=>`<a class="menu-item" href="${e(safeUrl(t.url,base))}" data-web="${e(t.url)}">${icon('book')} ${t.kind==='tos'?'Terms of Service':'Privacy Policy'} ${icon('arrow')}</a>`).join('')}<label class="check-label"><input type="checkbox" name="accept" required> I have reviewed and accept the documents listed above.</label><div class="form-error" role="alert"></div><button class="primary">Accept and continue</button></form><button class="text-button" data-action="logout">Sign out</button></div></div>`;return;
  }
  const u=state.user;
  main.innerHTML=`<div class="page"><div class="account-panel"><div class="account-heading"><div class="avatar">${e(u.name.slice(0,1))}</div><div><h1>Hello, ${e(u.name.split(' ')[0])}.</h1><p class="muted">${e(u.email)}</p></div></div><span class="badge">${e(u.role||'member')}</span><div class="menu-list"><a class="menu-item" href="#saved">${icon('heart')}<span>Saved places<small>Your personal collection</small></span>${icon('arrow')}</a><a class="menu-item" href="#offers">${icon('offers')}<span>Offers & inquiries<small>Keep track of your conversations</small></span>${icon('arrow')}</a><a class="menu-item" href="${e(base)}/dashboard" data-web="/dashboard">${icon('home')}<span>Member & host dashboard<small>Listings, documents and contracts on the website</small></span>${icon('arrow')}</a><button class="menu-item" data-action="password">${icon('lock')}<span>Change password<small>Keep your account secure</small></span>${icon('arrow')}</button><a class="menu-item" href="#support">${icon('help')}<span>A little help<small>We’re here when you need us</small></span>${icon('arrow')}</a><a class="menu-item" href="${e(base)}/legal/privacy" data-web="/legal/privacy">${icon('book')}<span>Privacy & terms</span>${icon('arrow')}</a></div><button class="secondary full" data-action="logout">Sign out</button><p class="footnote">Vaytoven · Find your place anywhere.<br>Website account sessions are separate from app sign-in.</p></div></div>`;
}
function support() {
  main.innerHTML=`<div class="page"><div class="help-intro"><div class="eyebrow">A little help along the way</div><h1>Good journeys start<br>with a conversation.</h1><p>Ask about Vaytoven, finding a place, or listing your property.</p><div class="support-options"><a href="${e(base)}/help" data-web="/help">${icon('book')}<strong>Find an answer</strong><small>Explore the help center</small></a><a href="${e(base)}/contact" data-web="/contact">${icon('help')}<strong>Talk to our team</strong><small>Send us a message</small></a></div><div id="chat-list" class="chat-list" role="log" aria-live="polite"></div><form id="chat-form" class="chat-form"><input name="message" aria-label="Message to Vaytoven support" placeholder="What’s on your mind?" required maxlength="4000"><button class="primary" aria-label="Send message">${icon('send')}</button></form><p class="footnote">AI assistance can make mistakes. Our team can help with account-specific questions.</p></div></div>`;
  renderChat();
}
function renderChat() {
  const el=document.querySelector('#chat-list');if(!el)return;
  el.innerHTML=state.chat.length?state.chat.map(m=>`<div class="chat-message ${m.role==='user'?'user':''}">${e(m.text)}</div>`).join(''):'<div class="chat-message">Hello, explorer. How can we help you find your place?</div>';
}
function modal(title,content){document.querySelector('#dialog-content').innerHTML=`<div class="dialog-head"><h2>${e(title)}</h2><button data-action="close-dialog" aria-label="Close dialog">${icon('close')}</button></div>${content}`;dialog.showModal();}
async function openWeb(path) {
  const url=safeUrl(path,base);if(!url||new URL(url).origin!==new URL(base).origin){toast('That link is unavailable.');return;}
  if(native) await plugin('Browser').open({url}); else window.open(url,'_blank','noopener,noreferrer');
}
async function route() {
  const id=++state.route;const [view='explore',value]=location.hash.slice(1).split('/');
  if(view==='destination'){state.query=decodeURIComponent(value||'');location.hash='explore';return;}
  const tab=view==='property'||view==='results'?'explore':view;
  document.querySelector('#navigation').innerHTML=tabs.map(([key,i,label])=>`<a href="#${key}" class="${tab===key?'active':''}" ${tab===key?'aria-current="page"':''}>${icon(i)}<span>${label}</span></a>`).join('');
  main.innerHTML=loading();window.scrollTo(0,0);
  try {
    if(view==='property' && /^\d+$/.test(value))await detail(id,value);
    else if(view==='saved')await saved(id);
    else if(view==='offers')await offers(id);
    else if(view==='account')await account(id);
    else if(view==='support')support();
    else await explore(id);
  } catch(error){if(id===state.route)main.innerHTML=`<div class="page">${errorBlock(error)}</div>`;}
}
async function saveProperty(id,button) {
  if(!state.user){toast('Sign in to start your collection.');location.hash='account';return;}
  const saved=state.saved.some(p=>p.id===id);
  await api(`mobile/saved/${id}`,{method:saved?'DELETE':'PUT'});
  state.saved=(await api('mobile/saved')).data;
  button.classList.toggle('saved',!saved);button.setAttribute('aria-pressed',String(!saved));button.setAttribute('aria-label',`${saved?'Save':'Unsave'} property`);
  toast(saved?'Removed from your saved places.':'A little possibility, saved.');
  if(location.hash==='#saved')await route();
}
function offerModal(kind) {
  if(!state.user){toast('Sign in to connect with the owner.');location.hash='account';return;}
  const p=state.property;const today=new Date().toLocaleDateString('en-CA');
  modal(kind==='offer'?'Make your next move':'Ask the owner',`<p class="form-hint">${e(p.title)}</p><div class="divider"></div><form id="offer-form" data-kind="${kind}" class="form-stack">${kind==='offer'?'<div><label for="amount">Your offer (USD)</label><input id="amount" name="amount" inputmode="decimal" placeholder="0.00" required pattern="[0-9]+(\\.[0-9]{1,2})?"></div>':''}<div class="form-row"><div><label for="check-in">Arrival (optional)</label><input id="check-in" name="check_in" type="date" min="${today}"></div><div><label for="check-out">Departure</label><input id="check-out" name="check_out" type="date" min="${today}"></div></div><div><label for="guests">Guests (optional)</label><input id="guests" name="guests" type="number" min="1" max="50" placeholder="How many of you?"></div><div><label for="offer-message">A note to the owner</label><textarea id="offer-message" name="message" placeholder="Tell them a little about your plans…" maxlength="2000"></textarea></div><p class="form-hint">Offers are open for 24 hours. This is an inquiry, not a reservation or payment.</p><div class="form-error" role="alert"></div><button class="primary">${kind==='offer'?'Send offer':'Send inquiry'} ${icon('arrow')}</button></form>`);
}
document.addEventListener('click',async event=>{
  const web=event.target.closest('[data-web]');if(web){event.preventDefault();try{await openWeb(web.dataset.web);}catch{toast('Unable to open the page. Please try again.');}return;}
  const button=event.target.closest('[data-action]');if(!button)return;const action=button.dataset.action;
  button.disabled=true;
  try{
    if(action==='scroll-results'){event.preventDefault();document.querySelector('#results')?.scrollIntoView({behavior:'smooth'});}
    if(action==='retry')await route();
    if(action==='destination'){state.query=button.dataset.query;await route();}
    if(action==='clear-filters'){if(dialog.open)dialog.close();state.query='';state.capacity='';state.price='';await route();}
    if(action==='more')await fetchProperties(state.route,state.meta.current_page+1);
    if(action==='save')await saveProperty(Number(button.dataset.id),button);
    if(action==='auth-switch')main.innerHTML=`<div class="page">${authForm(button.dataset.register==='true')}</div>`;
    if(action==='close-dialog')dialog.close();
    if(action==='offer'||action==='inquire')offerModal(action==='offer'?'offer':'inquiry');
    if(action==='photo-next'||action==='photo-prev'){
      state.photo=(state.photo+(action==='photo-next'?1:-1)+state.property.photos.length)%state.property.photos.length;
      document.querySelector('#detail-photo').src=safeUrl(state.property.photos[state.photo].url,base);
      document.querySelector('#photo-count').textContent=`${state.photo+1} / ${state.property.photos.length}`;
    }
    if(action==='share'){
      const url=safeUrl(state.property.public_url,base);const share={title:state.property.title,url};
      if(native)await plugin('Share').share(share);else if(navigator.share)await navigator.share(share);else {await navigator.clipboard.writeText(url);toast('Property link copied.');}
    }
    if(action==='password')main.innerHTML=`<div class="page">${passwordForm()}</div>`;
    if(action==='logout'){await api('auth/logout',{method:'POST'});resetAccount();toast('You’re signed out.');await route();}
    if(action==='offers-prev')await offers(state.route,state.offerPage-1);
    if(action==='offers-next')await offers(state.route,state.offerPage+1);
    if(action==='respond')modal(button.dataset.decision==='accepted'?'Accept this offer?':'Decline this offer?',`<form id="respond-form" data-id="${button.dataset.id}" data-decision="${button.dataset.decision}" class="form-stack"><p class="form-hint">Your response is shared with the buyer. Arrangements and payment remain between you and the guest.</p><div><label for="notes">A note to the buyer (optional)</label><textarea id="notes" name="notes" maxlength="2000"></textarea></div><div class="form-error" role="alert"></div><button class="primary">Confirm ${button.dataset.decision==='accepted'?'acceptance':'decline'}</button></form>`);
    if(action==='filters')modal('Your kind of getaway',`<form id="filter-form" class="form-stack"><div><label for="capacity">Space for</label><select id="capacity" name="capacity"><option value="">Any group size</option>${[2,4,6,8,10,12].map(n=>`<option value="${n}" ${state.capacity===String(n)?'selected':''}>${n}+ guests</option>`).join('')}</select></div><div><label for="max-price">Maximum asking price (USD)</label><input id="max-price" name="price" inputmode="decimal" placeholder="Any price" value="${state.price?e(Number(state.price)/100):''}"></div><div class="form-error" role="alert"></div><button class="primary">Find my places</button><button class="text-button" type="button" data-action="clear-filters">Clear filters</button></form>`);
  }catch(error){if(error.name!=='AbortError')toast(error.message);}finally{button.disabled=false;}
});
document.addEventListener('submit',async event=>{
  const form=event.target;event.preventDefault();const data=Object.fromEntries(new FormData(form));const button=form.querySelector('button[type="submit"],button:not([type])');const errorEl=form.querySelector('.form-error');
  if(errorEl)errorEl.textContent='';if(button)button.disabled=true;
  try{
    if(form.id==='search-form'){state.query=String(data.q).trim();await route();}
    if(form.id==='filter-form'){state.capacity=data.capacity;state.price=data.price?String(dollarsToCents(data.price)):'';dialog.close();await route();}
    if(form.id==='auth-form'){
      const register=form.dataset.register==='true';const response=await api(`auth/${register?'register':'login'}`,{method:'POST',body:{...data,device_name:`Vaytoven ${platform}`}});
      state.token=response.token;state.user=response.user;await route();toast(register?'Welcome to Vaytoven.':'Welcome back.');
    }
    if(form.id==='password-form'){await api('mobile/password',{method:'POST',body:data});await route();toast('Your password has been updated.');}
    if(form.id==='terms-form'){await api('mobile/terms',{method:'POST',body:{accept:true,version_ids:state.terms.map(t=>t.id)}});await loadAccount();location.hash='explore';toast('You’re ready to explore.');}
    if(form.id==='offer-form'){
      const body={kind:form.dataset.kind,message:data.message};if(body.kind==='offer')body.amount_cents=dollarsToCents(data.amount);
      if(data.check_in)body.check_in=data.check_in;if(data.check_out)body.check_out=data.check_out;if(data.guests)body.guests=Number(data.guests);
      await api(`mobile/properties/${state.property.id}/offers`,{method:'POST',body});dialog.close();location.hash='offers';toast('Sent to the owner. Your next chapter awaits.');
    }
    if(form.id==='respond-form'){await api(`mobile/offers/${form.dataset.id}/respond`,{method:'POST',body:{decision:form.dataset.decision,notes:data.notes}});dialog.close();await route();toast('Your response has been sent.');}
    if(form.id==='chat-form'){
      const message=String(data.message).trim();if(!message)return;state.chat.push({role:'user',text:message});form.reset();renderChat();
      try{const reply=await api('support/chat',{method:'POST',body:{message,visitor_id:state.visitor,...(state.chatId?{session_id:state.chatId}:{})}});state.chatId=reply.session_id;state.chat.push({role:'assistant',text:reply.reply});}
      catch(error){state.chatId=error.data?.session_id||state.chatId;state.chat.push({role:'assistant',text:error.message});}
      renderChat();
    }
  }catch(error){if(errorEl)errorEl.textContent=error.message;else toast(error.message);}finally{if(button)button.disabled=false;}
});
main.addEventListener('error',event=>{if(event.target instanceof HTMLImageElement&&!event.target.classList.contains('no-image')){event.target.classList.add('no-image');event.target.src='brand.svg';}},true);
function connectivity(){document.querySelector('#offline').hidden=navigator.onLine;}
window.addEventListener('online',connectivity);window.addEventListener('offline',connectivity);window.addEventListener('hashchange',()=>{if(dialog.open)dialog.close();route();});
if(native){plugin('App').addListener('backButton',()=>{if(dialog.open)dialog.close();else if(location.hash&&location.hash!=='#explore')location.hash='explore';else plugin('App').minimizeApp();});}
connectivity();route();
