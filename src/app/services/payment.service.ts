import { Injectable } from '@angular/core';
import { Booking } from './booking.service';
@Injectable({providedIn:'root'}) export class PaymentService {
 simulate(outcome:'success'|'pending'|'failed'):Booking['status'] {return outcome==='success'?'Confirmed':outcome==='pending'?'Payment pending':'Payment failed';}
}
