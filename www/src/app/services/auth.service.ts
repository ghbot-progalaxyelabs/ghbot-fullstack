import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

export interface User {
  id: string;
  email: string;
  name: string;
  avatarUrl: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  status: BehaviorSubject<boolean> = new BehaviorSubject(false);
  user: BehaviorSubject<User | null> = new BehaviorSubject<User | null>(null);

  constructor() {
    // Check if user is already authenticated on service initialization
    const token = localStorage.getItem('authToken');
    if (token) {
      this.status.next(true);
      // Restore user data from localStorage
      const userData = this.getUserData();
      if (userData) {
        this.user.next(userData);
      }
    }
  }

  setUserData(user: User): void {
    this.user.next(user);
    localStorage.setItem('userData', JSON.stringify(user));
  }

  getUserData(): User | null {
    const userData = localStorage.getItem('userData');
    if (userData) {
      return JSON.parse(userData);
    }
    return null;
  }

  clearUserData(): void {
    this.user.next(null);
    localStorage.removeItem('userData');
  }

  getAuthToken(): string | null {
    return localStorage.getItem('authToken');
  }
}
