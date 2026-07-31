Subject: Jobs Module — Updates Ready for Testing

Hi [Client Name],

Hope you're having a great day.

I'm reaching out to let you know we've completed a significant round of improvements on the Jobs module. The changes are live and ready for your review. Here's what's new:

---

**What to test:**

1. **New Job Form**
   - Try creating a new job from scratch — the form should save smoothly without any errors
   - Edit an existing job and save — updates should work without issues
   - Delete a job and confirm it removes properly

2. **HAWB / HBL and MAWB / MBL Fields**
   - These have been upgraded from small text boxes to expandable text areas
   - Type multiple lines and press Enter — the field should grow automatically to fit your content

3. **Date Pickers**
   - Click the calendar icon next to any date field — the calendar should now open
   - The calendar popup should display clearly without any overlapping icons or buttons

4. **Dimensions & Calculations**
   - When you add dimension rows (length, width, height, pieces), the totals at the bottom (Total CBM, Total Volume, Total Pieces) should calculate correctly
   - Try removing a dimension row — all totals should update immediately
   - The default quantity now starts at 0 instead of 1

5. **Country & Port Dropdowns**
   - All country selections work with the full global country list
   - Each port dropdown (Port of Loading and Port of Destination) now has a "+" button
   - Click the "+" to add a new port — the country should be pre-selected based on the dropdown you're using
   - The new port should only appear in the dropdown you clicked, not both

6. **Job Listing Page**
   - The date column now shows in a readable format like "06 Feb 2026"
   - Jobs should load quickly and display all columns correctly

7. **View Job Page**
   - The Edit, Approve, Reject, and Cancel buttons are now positioned on the right side
   - When viewing a job, the approval status badge shows correctly

8. **Job Approval Workflow**
   - A job can be sent for approval
   - Approved or rejected actions should work without any security errors

9. **Sidebar — Quick Access**
   - The "Countries" link is now available under Shipping > Settings for quick access to the full country list

---

**Note:** To ensure all the latest fixes are in place, please confirm the following has been run on your server:

```bash
cd public_html
git pull origin main
mysql -u YOUR_USER -p YOUR_DATABASE < migrations/jobs_fix_live_20260731.sql
```

We've put a lot of care into making sure everything works smoothly. Please take some time to run through these areas, and let us know if you spot anything that doesn't feel right.

Looking forward to your feedback!

Best regards,
Haizon Development Team
