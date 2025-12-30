<?php
// Start output buffering to prevent any unwanted output
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Letter of Conditional Acceptance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 40px;
            background-color: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            margin-bottom: 10px;
        }
        
        .ref-number {
            text-align: right;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .date {
            text-align: right;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .address {
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .subject {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        .program-details {
            margin: 20px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #2E8B57;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        table th {
            background-color: #f2f2f2;
        }
        
        .fees-table {
            margin: 30px 0;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #e8f5e8;
        }
        
        .terms-section {
            margin-top: 50px;
            page-break-before: always;
        }
        
        .terms-title {
            text-decoration: underline;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        .signature {
            margin-top: 100px;
        }
        
        .signature-img {
            width: 150px;
            height: 60px;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
        
        @media print {
            body {
                font-size: 12pt;
            }
            
            .terms-section {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="<?php echo $logo_path; ?>" class="logo" alt="LSUC Logo">
        <h2>LUSAKA SOUTH COLLEGE</h2>
        <p>123 University Road, Lusaka, Zambia</p>
    </div>    
    
    <div class="ref-number">
        REF: LSUC/ENROLL/<?php echo str_pad($application['id'], 3, '0', STR_PAD_LEFT); ?>
    </div>
    
    <div class="date">
        Date: <?php echo date('d/m/Y'); ?>
    </div>    
    
    <div class="address">
        <strong><?php echo htmlspecialchars($application['full_name']); ?></strong><br>
        <?php if (!empty($application['email'])): ?>
            <?php echo htmlspecialchars($application['email']); ?><br>
        <?php endif; ?>
        <?php if (!empty($application['phone'])): ?>
            <?php echo htmlspecialchars($application['phone']); ?><br>
        <?php endif; ?>
        <?php if (!empty($application['nationality'])): ?>
            <?php echo htmlspecialchars($application['nationality']); ?>
        <?php endif; ?>
    </div>    
    
    <p>Dear <?php echo htmlspecialchars($application['full_name']); ?>,</p>
    
    <div class="subject">
        RE: LETTER OF CONDITIONAL ACCEPTANCE OF ENROLMENT <?php echo date('Y') + 1; ?> INTAKE
    </div>    
    
    <p>
        Reference is made to your application for enrolment onto the January Intake <?php echo date('Y') + 1; ?> at 
        Lusaka South College (LSC). The Council of LSC considered your application and 
        I am pleased to inform you that you have been accepted to pursue the following programme:
    </p>    
    
    <div class="program-details">
        <p><strong>Programme:</strong> <?php echo htmlspecialchars($application['programme_name']); ?></p>
        <?php if (!empty($application['duration'])): ?>
            <p><strong>Duration:</strong> <?php echo htmlspecialchars($application['duration']); ?></p>
        <?php endif; ?>
        <?php if (!empty($application['mode_of_learning'])): ?>
            <p><strong>Mode of Study:</strong> <?php 
                echo $application['mode_of_learning'] === 'online' ? 'Online' : 'Physical'; 
            ?></p>
        <?php endif; ?>
        <p><strong>Intake:</strong> <?php echo htmlspecialchars($application['intake_name']); ?></p>
    </div>    
    
    <h4>Programme Fees</h4>
    <table class="fees-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount (K)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($grouped_fees['one_time'])): ?>
                <?php foreach ($grouped_fees['one_time'] as $fee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                        <td><?php echo number_format($fee['fee_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($grouped_fees['per_term'])): ?>
                <?php foreach ($grouped_fees['per_term'] as $fee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                        <td><?php echo number_format($fee['fee_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($grouped_fees['per_year'])): ?>
                <?php foreach ($grouped_fees['per_year'] as $fee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                        <td><?php echo number_format($fee['fee_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <tr class="total-row">
                <td><strong>Total Fees</strong></td>
                <td><strong><?php echo number_format($total_fees, 2); ?></strong></td>
            </tr>
        </tbody>
    </table>
    
    <h4>Other Fees</h4>
    <p><em>(Students have an option to purchase on their own)</em></p>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount (K)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Accommodation Per Term (Option)</td>
                <td>3,900.00</td>
            </tr>
        </tbody>
    </table>    
    
    <p>
        <em>
            Note that Identity Cards and Library fees are paid once per year.<br>
            Students' normal registration shall commence on <strong>5th January <?php echo date('Y') + 1; ?></strong> to 
            <strong>31st January <?php echo date('Y') + 1; ?></strong> and <strong>late Registration from 1st to 27th February <?php echo date('Y') + 1; ?></strong> 
            and will attract a <strong>penalty fee of K500.00</strong>. Classes will commence on 
            <strong>26th January <?php echo date('Y') + 1; ?></strong>. Please refer to the academic calendar attached for details. 
            We congratulate you on your conditional acceptance at Lusaka South College. Find attached 
            the Terms and Conditions for your necessary action and seek clarification should the need arise.
        </em>
    </p>
    
    <h4>Bank Details are as follows:</h4>
    <p>
        <strong>Account Name:</strong> LUSAKA SOUTH COLLEGE<br>
        <strong>Account Number:</strong> 5947236500193 (ZMW)<br>
        <strong>Bank:</strong> ZANACO<br>
        <strong>Branch Name:</strong> ACACIA PARK BRANCH<br>
        <strong>Branch Code:</strong> 086<br>
        <strong>Sort Code:</strong> 010086<br>
        <strong>Swift Code:</strong> ZNCOZMLU
    </p>    
    
    <p>Your conditional acceptance is contingent upon the following:</p>
    <ol>
        <li>Receipt by the College of 100% of the total fees plus all other fees as indicated in the registration terms and conditions. <strong>Or</strong> Minimum of 60% Payment.</li>
        <li>Payment of 100% Exemptions Fees</li>
        <li>Attached Proof of National Registration Card or Passport</li>
    </ol>    
    
    <p>
        We extend our best wishes and look forward to having you study with us. Note that registration 
        is in progress and you are advised to register and join the classes currently in session. 
        Attached is the student registration form, academic calendar and catalog of programmes for 
        the courses you will undertake in the programme.
    </p>    
    
    <div class="signature">
        <p>Yours Sincerely</p>
        <br><br><br>
        <img src="<?php echo $signature_path; ?>" class="signature-img" alt="Signature">
        <p>
            <strong>Dr Nelly Kunda</strong><br>
            Deputy Registrar - Academic<br>
            <strong>Lusaka South College</strong>
        </p>
    </div>    
    
    <div class="terms-section">
        <div class="terms-title">Terms and Conditions</div>
        <ol>
            <li>All New and Continuing students MUST be registered for the term by completing the semester registration form, prior to the end of week 2.</li>
            <li>Fees Published are fixed per term</li>
            <li>The Annual tuition fees will be split and payable in three terms of a year.</li>
            <li>All new and continuing students must pay a user fee every term upon registration. Students who withdraw from the College may receive a refund of the user fees if the withdrawal is because of failure on the part of the College to provide the student's chosen programme of study. In all other cases, the user fee is non-refundable.</li>
            <li>All New and continuing students must pay 100% semester tuition fees before commencement of the semester or according to the standard College payment plan.</li>
            <li>However, in the event that a student is unable to settle the full cost of fees at commencement, they should pay a minimum registration and user fees and then enter into a payment plan. The student will then be required to pay a minimum of 60% of the semester's tuition fees by the 2nd Week after commencement of the semester.</li>
            <li>Students who choose a payment plan must pay 60% by the 2nd week, 80% by the 8th week and 100% by the 12th week.</li>
            <li>If a student fails to pay on agreed published payment dates (refer to 8 above) they will be subject to late payment fees or in some cases, will not be allowed to attend classes nor sit for any examinations, nor receive any services from the College.</li>
            <li>In the event of any withdrawal from the College or change of programme, there will be no refund on Tuition, and/or Exemption fee unless; a) The College is not able to run the programme. b) visa refusal c) withdrawal prior to start of study up to week 4. All other refunds are subject to the withdrawal policy.</li>
            <li>Programmes will only run if justified by demand.</li>
            <li>LSC Management will be happy to offer advice relating to College application, examination entries, etc., however it is the student's own responsibility to ensure that all applications, registration of entries, of whatever nature, are in order and sent off by the appropriate closing date.</li>
        </ol>
    </div>
    
    <div class="footer">
        <p>This is a computer-generated document and is valid without signature.</p>
        <p>Lusaka South College &copy; <?php echo date('Y'); ?> - All Rights Reserved</p>
    </div>
</body>
</html>