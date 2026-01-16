// Email simulation for testing when PHP server is not available
function simulateEmailSubmission(formData, formType) {
    return new Promise((resolve, reject) => {
        // Simulate network delay
        setTimeout(() => {
            // Validate required fields
            const requiredFields = ['firstname', 'lastname', 'email'];
            const missingFields = requiredFields.filter(field => !formData[field]);
            
            if (missingFields.length > 0) {
                reject({
                    success: false,
                    message: `Missing required fields: ${missingFields.join(', ')}`
                });
                return;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(formData.email)) {
                reject({
                    success: false,
                    message: 'Invalid email format'
                });
                return;
            }
            
            // Generate mock application ID
            const applicationId = 'LSUC-' + formType.toUpperCase() + '-2025-' + 
                                  Math.random().toString().substr(2, 6);
            
            // Simulate successful submission
            const emailContent = generateMockEmailContent(formData, formType, applicationId);
            
            // Log the email content for verification
            console.log('=== SIMULATED EMAIL CONTENT ===');
            console.log('To: admissions@lsuczm.com');
            console.log('Subject:', emailContent.subject);
            console.log('Body:', emailContent.body);
            console.log('=== END EMAIL CONTENT ===');
            
            resolve({
                success: true,
                message: 'Application submitted successfully! (Simulated)',
                applicationId: applicationId,
                emailContent: emailContent
            });
        }, 1500); // Simulate 1.5 second delay
    });
}

function generateMockEmailContent(data, formType, applicationId) {
    let subject, body;
    
    switch (formType) {
        case 'undergraduate':
            subject = `New Undergraduate Application - ${data.firstname} ${data.lastname}`;
            body = `
                NEW UNDERGRADUATE APPLICATION
                =============================
                Application ID: ${applicationId}
                
                PERSONAL INFORMATION:
                Name: ${data.firstname} ${data.lastname}
                Email: ${data.email}
                Phone: ${data.phone}
                NRC/Passport: ${data.nrc || 'Not provided'}
                Date of Birth: ${data.dateofbirth || 'Not provided'}
                Address: ${data.address || 'Not provided'}
                
                ACADEMIC INFORMATION:
                Preferred Program: ${data.program}
                Preferred Intake: ${data.intake || 'Not specified'}
                Previous School: ${data.previousschool || 'Not provided'}
                Grade 12 Results: ${data.grade12results || 'Not provided'}
                
                EMERGENCY CONTACT:
                Guardian Name: ${data.guardianname || 'Not provided'}
                Guardian Phone: ${data.guardianphone || 'Not provided'}
                Relationship: ${data.relationship || 'Not provided'}
                
                Submitted: ${new Date().toLocaleString()}
            `;
            break;
            
        case 'short-courses':
            subject = `New Short Course Application - ${data.firstname} ${data.lastname}`;
            body = `
                NEW SHORT COURSE APPLICATION
                ============================
                Application ID: ${applicationId}
                
                PERSONAL INFORMATION:
                Name: ${data.firstname} ${data.lastname}
                Email: ${data.email}
                Phone: ${data.phone}
                Current Occupation: ${data.occupation || 'Not specified'}
                
                COURSE INFORMATION:
                Selected Course: ${data.course}
                Preferred Start Date: ${data.startdate || 'Not specified'}
                Mode of Study: ${data.schedule || 'Not specified'}
                Relevant Experience: ${data.experience || 'Not provided'}
                Learning Goals: ${data.goals || 'Not provided'}
                
                Submitted: ${new Date().toLocaleString()}
            `;
            break;
            
        case 'corporate-training':
            subject = `New Corporate Training Request - ${data.company}`;
            body = `
                NEW CORPORATE TRAINING REQUEST
                ==============================
                Request ID: ${applicationId}
                
                ORGANIZATION INFORMATION:
                Company: ${data.company}
                Industry: ${data.industry || 'Not specified'}
                Company Size: ${data.companysize || 'Not specified'}
                Address: ${data.address || 'Not provided'}
                
                CONTACT PERSON:
                Name: ${data.contactname || data.firstname + ' ' + data.lastname}
                Position: ${data.position || 'Not specified'}
                Email: ${data.email}
                Phone: ${data.phone}
                
                TRAINING REQUIREMENTS:
                Training Type: ${data.trainingtype}
                Number of Participants: ${data.participants || 'Not specified'}
                Duration: ${data.duration || 'Not specified'}
                Location: ${data.location || 'Not specified'}
                Budget Range: ${data.budget || 'Not specified'}
                Specific Needs: ${data.specificneeds || 'Not provided'}
                Timeline: ${data.timeline || 'Not specified'}
                
                Submitted: ${new Date().toLocaleString()}
            `;
            break;
            
        default:
            subject = `New Application - ${data.firstname} ${data.lastname}`;
            body = `
                NEW APPLICATION
                ===============
                Application ID: ${applicationId}
                
                Form Data:
                ${JSON.stringify(data, null, 2)}
                
                Submitted: ${new Date().toLocaleString()}
            `;
    }
    
    return { subject, body };
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { simulateEmailSubmission, generateMockEmailContent };
}