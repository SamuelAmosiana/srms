# Payment System Documentation

## Overview
This payment system integrates Swish Mobile Money into the Student Records Management System (SRMS) with a controlled, auditable payment workflow.

## Architecture

### Public Payment Flow
1. Public users access `/make_payment.php` to initiate payments
2. Form captures: Full Name, Phone Number, Amount, Payment Category, Description, Student ID
3. Two submission options:
   - "Make Payment (Swish)": Initiates Swish payment (STK Push) and saves transaction as PENDING
   - "Submit Payment": Submits details for finance review and redirects to internal tracking

### Internal Finance Flow
1. Finance/Admin staff access `/finance/track_payment.php`
2. View all submitted payments in a table format
3. Process payments with "Confirm" or "Reject" actions
4. Confirmed payments automatically record as income

## Database Schema

### payments table
- id: Auto-increment primary key
- transaction_reference: Unique transaction identifier
- full_name: Payer's full name
- phone_number: Mobile money phone number
- amount: Payment amount
- category: Payment category (Application Fee, Tuition/School Fees, Other)
- description: Payment description
- student_or_application_id: Associated student/application ID
- status: Payment status (pending, confirmed, rejected)
- source: Source of payment (public, srms)
- created_at: Creation timestamp
- updated_at: Last update timestamp

### finance_income table
- id: Auto-increment primary key
- transaction_reference: Reference to payment
- amount: Income amount
- category: Income category
- recorded_by: User ID of person who recorded
- recorded_at: Recording timestamp

### payment_logs table
- id: Auto-increment primary key
- transaction_reference: Reference to payment
- raw_response: Raw response from payment provider
- action_taken: Action performed (initiated, confirmed, rejected, etc.)
- created_at: Log timestamp

## API Endpoints

### /api/process_swish_payment.php
Handles Swish payment initiation and returns payment instructions.

### /api/swish_callback.php
Processes callbacks from Swish payment provider and updates payment status accordingly.

## Security Features

1. Input validation and sanitization
2. Authentication controls for internal pages
3. Audit trail through payment logs
4. Prevention of duplicate confirmations
5. Protection against direct access to internal pages

## Implementation Notes

1. The system separates payment initiation from confirmation
2. Only confirmed payments are recorded as income
3. Comprehensive logging for audit purposes
4. Responsive UI for both public and internal users
5. Compatible with shared hosting environments