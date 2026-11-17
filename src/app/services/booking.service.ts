import { api } from './api';
import { Injectable, signal } from '@angular/core';
export interface Booking { reference:string; email:string; serviceId:number; serviceName:string; date:string; time:string; quantity:number; total:number; status:'Awaiting payment'|'Confirmed'|'Payment pending'|'Payment failed'|'Cancelled'; paymentMethod?:string; }
export interface ServiceOption { id:number; name:string; description:string; price:number; icon:string; }
// Philippine time is UTC+08:00 throughout the year.
export function manilaDateTime(now = Date.now()): string {
 return new Date(now + 8 * 60 * 60 * 1000).toISOString().slice(0,16);
}
export function checkInError(date:string,time:string,now = Date.now()):string {
 const parsed = new Date(date+'T00:00:00Z');
 if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || Number.isNaN(parsed.getTime()) || parsed.toISOString().slice(0,10)!==date || date<manilaDateTime(now).slice(0,10)) return 'Choose today or a future date.';
 if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) return 'Select a valid check-in time.';
 const checkin = Date.parse(date+'T'+time+':00+08:00');
 if (!Number.isFinite(checkin) || checkin<=now) return 'Choose a check-in date and time in the future (Philippine time).';
 return '';
}
@Injectable({providedIn:'root'}) export class BookingService {
 private serviceState=signal<ServiceOption[]>([
 {id:1,name:'Cozy Studio',description:'Comfortable stay for solo guests and couples',price:1800,icon:'bed-outline'},
 {id:2,name:'Deluxe Suite',description:'Spacious suite with premium amenities',price:3200,icon:'home-outline'},
 {id:3,name:'Family Room',description:'Extra space for family stays',price:4500,icon:'people-outline'}]);
 get services(){return this.serviceState();}
 private draftState=signal<Booking|null>(null);
 private saved=false;
 get draft(){return this.draftState();}
 set draft(value:Booking|null){this.draftState.set(value);this.saved=!!value;}
 form={selected:1,date:'',time:'14:00',quantity:1};
 private records=signal<Booking[]>([]);
 private loadVersion=0;
 constructor(){api<ServiceOption[]>('services').then(s=>this.serviceState.set(s.map(x=>({...x,id:Number(x.id),price:Number(x.price)})))).catch(()=>{});}
 async load(){const version=++this.loadVersion;const rows=await api<Booking[]>('bookings');if(version===this.loadVersion)this.records.set(rows.map(b=>({...b,serviceId:Number(b.serviceId),total:Number(b.total),quantity:Number(b.quantity)})));}
 clear(){++this.loadVersion;this.records.set([]);this.draft=null;this.form={selected:1,date:'',time:'14:00',quantity:1};}
 start(serviceId:number){this.draft=null;this.form={selected:Number(serviceId),date:'',time:'14:00',quantity:1};}
 create(email:string,serviceId:number,date:string,time:string,quantity:number):string {
 const svc=this.services.find(x=>x.id===Number(serviceId));
 if(!svc)return 'Select an accommodation.';
 const validation=checkInError(date,time);if(validation)return validation;
 if(!Number.isInteger(Number(quantity))||Number(quantity)<1||Number(quantity)>10)return 'Rooms must be between 1 and 10.';
 this.draftState.set({reference:'',email,serviceId:svc.id,serviceName:svc.name,date,time,quantity:Number(quantity),total:svc.price*Number(quantity),status:'Awaiting payment'});this.saved=false;
 return '';
 }
 async saveDraft(){if(!this.draft)throw new Error('No booking selected.');const validation=checkInError(this.draft.date,this.draft.time);if(validation)throw new Error(validation);if(this.saved)return;this.draft=await api<Booking>('create',this.draft);const b=this.draft!;++this.loadVersion;this.records.update(rows=>[b,...rows.filter(r=>r.reference!==b.reference)]);}
 async updatePayment(reference:string,outcome:string,method:string){await api('pay',{reference,outcome,method});++this.loadVersion;const status:Booking['status']=outcome==='success'?'Confirmed':outcome==='pending'?'Payment pending':'Payment failed';this.records.update(rows=>rows.map(b=>b.reference===reference?{...b,status,paymentMethod:method}:b));}
 async cancel(reference:string){await api('cancel',{reference});++this.loadVersion;this.records.update(rows=>rows.map(b=>b.reference===reference?{...b,status:'Cancelled'}:b));if(this.draft?.reference===reference)this.draft=null;}
 forUser(email:string){return this.records().filter(b=>b.email===email);}
}
