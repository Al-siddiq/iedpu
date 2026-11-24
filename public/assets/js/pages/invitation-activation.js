'use strict';
(() => {
 const root=document.getElementById('invitation-activation');
 const form=document.getElementById('activate');
 const status=document.getElementById('status');
 if(!root||!form||!status)return;
 form.addEventListener('submit',async event=>{
  event.preventDefault();status.textContent='Processing…';
  const userId=Number(form.elements.user_id.value);
  const token=form.elements.token.value;
  const password=form.elements.password.value;
  try{
   const csrfResponse=await fetch(root.dataset.csrfUrl,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
   if(!csrfResponse.ok)throw new Error('CSRF bootstrap failed');
   const csrf=await csrfResponse.json();
   if(!csrf?.data?.header||!csrf?.data?.token)throw new Error('Invalid CSRF response');
   const response=await fetch(root.dataset.activationUrl,{method:'POST',credentials:'same-origin',cache:'no-store',
    headers:{'Content-Type':'application/json',Accept:'application/json',[csrf.data.header]:csrf.data.token},
    body:JSON.stringify({user_id:userId,token,password})});
   form.elements.password.value='';form.elements.token.value='';
   status.textContent=response.ok?'Account activated. You can now sign in.':'Activation failed or the invitation expired. Contact the inviting officer.';
  }catch(_){form.elements.password.value='';status.textContent='Activation is temporarily unavailable. Try again later.';}
 });
})();
