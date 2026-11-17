import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { IonicModule } from '@ionic/angular/lazy';
import { AuthService } from '../services/auth.service';
import { Booking, BookingService, manilaDateTime, checkInError } from '../services/booking.service';
import { PaymentService } from '../services/payment.service';
@Component({selector:'app-page',standalone:true,imports:[CommonModule,FormsModule,IonicModule,RouterModule],templateUrl:'./page.component.html',styleUrls:['./page.component.scss']})
export class PageComponent {
 page='landing'; name='';email='';password=''; error=''; notice=''; method='Demo Wallet';outcome:'success'|'pending'|'failed'='success';busy=false;
 constructor(public auth:AuthService,public bookings:BookingService,public payment:PaymentService,private route:ActivatedRoute,private router:Router,private cd:ChangeDetectorRef){
 this.route.url.subscribe(parts=>{this.page=parts[0]?.path||'landing';this.cd.markForCheck();});
 }
 async ionViewWillEnter(){this.error='';this.notice='';try{await this.auth.ready;if(this.requiresAuth()&&!this.auth.current){await this.router.navigateByUrl('/login');return;}if(this.auth.current&&['home','my-bookings','payments'].includes(this.page))await this.bookings.load();}catch(e){this.error=this.message(e);}finally{this.cd.markForCheck();}}
 private message(e:unknown){return e instanceof Error?e.message:String(e);}
 requiresAuth(){return ['home','booking-form','booking-summary','payment','my-bookings','payments','profile'].includes(this.page);}
 go(path:string){this.error='';void this.router.navigateByUrl('/'+path);}
 get selected(){return this.bookings.form.selected;}set selected(v:number){this.bookings.form.selected=Number(v);}
 get date(){return this.bookings.form.date;}set date(v:string){this.bookings.form.date=v;}
 get time(){return this.bookings.form.time;}set time(v:string){this.bookings.form.time=v;}
 get quantity(){return this.bookings.form.quantity;}set quantity(v:number){this.bookings.form.quantity=Number(v);}
 async submitAuth(){if(this.busy)return;this.busy=true;try{this.bookings.clear();if(this.page==='register')await this.auth.register(this.name,this.email,this.password);else await this.auth.login(this.email,this.password);this.password='';this.go('home');}catch(e){this.error=this.message(e);}finally{this.busy=false;this.cd.markForCheck();}}
 choose(id:number){this.bookings.start(id);if(!this.auth.current){this.go('login');return;}this.go('booking-form');}
 submitBooking(){if(!this.auth.current){this.go('login');return;}this.error=checkInError(this.date,this.time);if(this.error)return;this.error=this.bookings.create(this.auth.current.email,this.selected,this.date,this.time,this.quantity);if(!this.error)this.go('booking-summary');}
 async confirm(){if(!this.bookings.draft||this.busy)return;this.error=this.draftTimeError;if(this.error)return;this.busy=true;try{await this.bookings.saveDraft();this.go('payment');}catch(e){this.error=this.message(e);}finally{this.busy=false;this.cd.markForCheck();}}
 async pay(){const b=this.bookings.draft;if(!b||this.busy)return;this.error=checkInError(b.date,b.time);if(this.error)return;this.busy=true;try{await this.bookings.updatePayment(b.reference,this.outcome,this.method);this.bookings.draft=null;this.go('my-bookings');}catch(e){this.error=this.message(e);}finally{this.busy=false;this.cd.markForCheck();}}
 async cancelBooking(b:Booking){if(this.busy||b.status==='Cancelled')return;if(!window.confirm('Cancel booking '+b.reference+'? This cannot be undone. No real payment or refund is processed in this demo.'))return;this.error='';this.notice='';this.busy=true;try{await this.bookings.cancel(b.reference);this.notice='Booking cancelled successfully.';}catch(e){this.error=this.message(e);}finally{this.busy=false;this.cd.markForCheck();}}
 get records(){return this.bookings.forUser(this.auth.current?.email||'');}
 get draftTimeError(){const b=this.bookings.draft;return b?checkInError(b.date,b.time):'';}
 get selectedService(){return this.bookings.services.find(s=>s.id===Number(this.selected));}
 get minDate(){return manilaDateTime().slice(0,10);}
 get minTime(){const now=Date.now();const today=manilaDateTime(now).slice(0,10);if(this.date!==today)return '00:00';const next=manilaDateTime(Math.floor(now/60000)*60000+60000);return next.slice(0,10)===today?next.slice(11,16):'23:59';}
}
