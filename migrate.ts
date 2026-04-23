import { PrismaClient, Prisma } from "@prisma/client";
import fs from "fs";

const prisma = new PrismaClient();

function toDate(val: string | null | undefined): Date | null {
  if (!val) return null;
  try { return new Date(val); } catch { return null; }
}
function toDecimal(val: any): Prisma.Decimal | null {
  if (val === null || val === undefined) return null;
  try { return new Prisma.Decimal(val); } catch { return null; }
}

async function upsertSafe(label: string, fn: () => Promise<any>) {
  try { await fn(); return true; }
  catch (e: any) {
    console.warn(`  ⚠ ${label}: ${e.message?.split('\n')[0]}`);
    return false;
  }
}

async function main() {
  const db: Record<string, any[]> = JSON.parse(fs.readFileSync("sm1_full_tables.json", "utf-8"));

  // Build valid ID sets for FK checks
  const personIds = new Set((db.person ?? []).map((r: any) => r.id));
  const driverIds = new Set((db.driver ?? []).map((r: any) => r.id));
  const vehicleIds = new Set((db.vehicle ?? []).map((r: any) => r.id));

  let ok = 0, skip = 0;

  // ── Make / Model / Color ───────────────────────────────────────────────
  console.log("→ make...");
  for (const r of db.make ?? []) {
    await upsertSafe(`make#${r.id}`, () => prisma.make.upsert({ where: { id: r.id }, update: {}, create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) } }));
  }
  console.log("→ model...");
  for (const r of db.model ?? []) {
    await upsertSafe(`model#${r.id}`, () => prisma.vehicleModel.upsert({ where: { id: r.id }, update: {}, create: { id: r.id, makeid: r.makeid, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) } }));
  }
  console.log("→ color...");
  for (const r of db.color ?? []) {
    await upsertSafe(`color#${r.id}`, () => prisma.color.upsert({ where: { id: r.id }, update: {}, create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) } }));
  }
  console.log("→ department...");
  for (const r of db.department ?? []) {
    await upsertSafe(`dept#${r.id}`, () => prisma.department.upsert({ where: { id: r.id }, update: {}, create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) } }));
  }
  console.log("→ cashtype...");
  for (const r of db.cashtype ?? []) {
    await upsertSafe(`cashtype#${r.id}`, () => prisma.cashType.upsert({ where: { id: r.id }, update: {}, create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) } }));
  }

  // ── PersonType ─────────────────────────────────────────────────────────
  console.log("→ persontype...");
  for (const r of db.persontype ?? []) {
    const res = await upsertSafe(`persontype#${r.id}`, () =>
      prisma.personType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── DriverType ─────────────────────────────────────────────────────────
  console.log("→ drivertype...");
  for (const r of db.drivertype ?? []) {
    const res = await upsertSafe(`drivertype#${r.id}`, () =>
      prisma.driverType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── EmployeeType ───────────────────────────────────────────────────────
  console.log("→ employeetype...");
  for (const r of db.employeetype ?? []) {
    const res = await upsertSafe(`employeetype#${r.id}`, () =>
      prisma.employeeType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── VehicleType ────────────────────────────────────────────────────────
  console.log("→ vehicletype...");
  for (const r of db.vehicletype ?? []) {
    const res = await upsertSafe(`vehicletype#${r.id}`, () =>
      prisma.vehicleType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TripType ────────────────────────────────────────────────────────────
  console.log("→ triptype...");
  for (const r of db.triptype ?? []) {
    const res = await upsertSafe(`triptype#${r.id}`, () =>
      prisma.tripType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TripStatus ──────────────────────────────────────────────────────────
  console.log("→ tripstatus...");
  for (const r of db.tripstatus ?? []) {
    const res = await upsertSafe(`tripstatus#${r.id}`, () =>
      prisma.tripStatus.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TripSource ──────────────────────────────────────────────────────────
  console.log("→ tripsource...");
  for (const r of db.tripsource ?? []) {
    const res = await upsertSafe(`tripsource#${r.id}`, () =>
      prisma.tripSource.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── PaymentType ─────────────────────────────────────────────────────────
  console.log("→ paymenttype...");
  for (const r of db.paymenttype ?? []) {
    const res = await upsertSafe(`paymenttype#${r.id}`, () =>
      prisma.paymentType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TicketCategory ──────────────────────────────────────────────────────
  console.log("→ ticketcategory...");
  for (const r of db.ticketcategory ?? []) {
    const res = await upsertSafe(`ticketcategory#${r.id}`, () =>
      prisma.ticketCategory.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TicketStatus ────────────────────────────────────────────────────────
  console.log("→ ticketstatus...");
  for (const r of db.ticketstatus ?? []) {
    const res = await upsertSafe(`ticketstatus#${r.id}`, () =>
      prisma.ticketStatus.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TicketType ──────────────────────────────────────────────────────────
  console.log("→ tickettype...");
  for (const r of db.tickettype ?? []) {
    const res = await upsertSafe(`tickettype#${r.id}`, () =>
      prisma.ticketType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TicketPriority ──────────────────────────────────────────────────────
  console.log("→ ticketpriority...");
  for (const r of db.ticketpriority ?? []) {
    const res = await upsertSafe(`ticketpriority#${r.id}`, () =>
      prisma.ticketPriority.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── JobTitle ────────────────────────────────────────────────────────────
  console.log("→ jobtitle...");
  for (const r of db.jobtitle ?? []) {
    const res = await upsertSafe(`jobtitle#${r.id}`, () =>
      prisma.jobTitle.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── LicenseCategory ────────────────────────────────────────────────────
  console.log("→ licensecategory...");
  for (const r of db.licensecategory ?? []) {
    const res = await upsertSafe(`licensecategory#${r.id}`, () =>
      prisma.licenseCategory.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── CompanyLegalForm ────────────────────────────────────────────────────
  console.log("→ companylegalform...");
  for (const r of db.companylegalform ?? []) {
    const res = await upsertSafe(`companylegalform#${r.id}`, () =>
      prisma.companyLegalForm.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── CompanyType ────────────────────────────────────────────────────────
  console.log("→ companytype...");
  for (const r of db.companytype ?? []) {
    const res = await upsertSafe(`companytype#${r.id}`, () =>
      prisma.companyType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Country ────────────────────────────────────────────────────────────
  console.log("→ country...");
  for (const r of db.country ?? []) {
    const res = await upsertSafe(`country#${r.id}`, () =>
      prisma.country.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, code: r.code, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Currency ────────────────────────────────────────────────────────────
  console.log("→ currency...");
  for (const r of db.currency ?? []) {
    const res = await upsertSafe(`currency#${r.id}`, () =>
      prisma.currency.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, code: r.code, symbol: r.symbol, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── OfficialType ────────────────────────────────────────────────────────
  console.log("→ officialtype...");
  for (const r of db.officialtype ?? []) {
    const res = await upsertSafe(`officialtype#${r.id}`, () =>
      prisma.officialType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TransactionType ────────────────────────────────────────────────────
  console.log("→ transactiontype...");
  for (const r of db.transactiontype ?? []) {
    const res = await upsertSafe(`transactiontype#${r.id}`, () =>
      prisma.transactionType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── TransactionEntityType ──────────────────────────────────────────────
  console.log("→ transactionentitytype...");
  for (const r of db.transactionentitytype ?? []) {
    const res = await upsertSafe(`transactionentitytype#${r.id}`, () =>
      prisma.transactionEntityType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, tablename: r.tablename, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── BankAccountType ────────────────────────────────────────────────────
  console.log("→ bankaccounttype...");
  for (const r of db.bankaccounttype ?? []) {
    const res = await upsertSafe(`bankaccounttype#${r.id}`, () =>
      prisma.bankAccountType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Bank ────────────────────────────────────────────────────────────────
  console.log("→ bank...");
  for (const r of db.bank ?? []) {
    const res = await upsertSafe(`bank#${r.id}`, () =>
      prisma.bank.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── DriverActivityType ──────────────────────────────────────────────────
  console.log("→ driveractivitytype...");
  for (const r of db.driveractivitytype ?? []) {
    const res = await upsertSafe(`driveractivitytype#${r.id}`, () =>
      prisma.driverActivityType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── VehicleServiceType ──────────────────────────────────────────────────
  console.log("→ vehicleservicetype...");
  for (const r of db.vehicleservicetype ?? []) {
    const res = await upsertSafe(`vehicleservicetype#${r.id}`, () =>
      prisma.vehicleServiceType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── VehicleServiceTask ──────────────────────────────────────────────────
  console.log("→ vehicleservicetask...");
  for (const r of db.vehicleservicetask ?? []) {
    const res = await upsertSafe(`vehicleservicetask#${r.id}`, () =>
      prisma.vehicleServiceTask.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── CashBoxType ─────────────────────────────────────────────────────────
  console.log("→ cashboxtype...");
  for (const r of db.cashboxtype ?? []) {
    const res = await upsertSafe(`cashboxtype#${r.id}`, () =>
      prisma.cashBoxType.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createddate: toDate(r.createddate), updateddate: toDate(r.updateddate) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Menu ────────────────────────────────────────────────────────────────
  console.log("→ menu...");
  for (const r of db.menu ?? []) {
    const res = await upsertSafe(`menu#${r.id}`, () =>
      prisma.menu.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, parentid: r.parentid, name: r.name, url: r.url, sortorder: r.sortorder, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── WorkingHoursSchicht ────────────────────────────────────────────────
  console.log("→ workinghoursschicht...");
  for (const r of db.workinghoursschicht ?? []) {
    const res = await upsertSafe(`workinghoursschicht#${r.id}`, () =>
      prisma.workingHoursSchicht.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, name: r.name, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── BankAccount ─────────────────────────────────────────────────────────
  console.log("→ bankaccount...");
  for (const r of db.bankaccount ?? []) {
    const res = await upsertSafe(`bankaccount#${r.id}`, () =>
      prisma.bankAccount.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, bankaccounttypeid: r.bankaccounttypeid, name: r.name, bankname: r.bankname, iban: r.iban, bic: r.bic, personid: r.personid, currencyid: r.currencyid, isactive: Boolean(r.isactive), note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── CashBox ─────────────────────────────────────────────────────────────
  console.log("→ cashbox...");
  for (const r of db.cashbox ?? []) {
    const res = await upsertSafe(`cashbox#${r.id}`, () =>
      prisma.cashBox.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, cashboxtypeid: r.cashboxtypeid, name: r.name, note: r.note, createddate: toDate(r.createddate), updateddate: toDate(r.updateddate) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Company ─────────────────────────────────────────────────────────────
  console.log("→ company...");
  for (const r of db.company ?? []) {
    const res = await upsertSafe(`company#${r.id}`, () =>
      prisma.company.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, companytypeid: r.companytypeid, companylegalformid: r.companylegalformid, name: r.name, street: r.street, housenr: r.housenr, pobox: r.pobox, city: r.city, countryid: r.countryid, phone: r.phone, email: r.email, website: r.website, vatid: r.vatid, taxid: r.taxid, bankname: r.bankname, iban: r.iban, bic: r.bic, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── DriverActivity ──────────────────────────────────────────────────────
  console.log("→ driveractivity...");
  for (const r of db.driveractivity ?? []) {
    const res = await upsertSafe(`driveractivity#${r.id}`, () =>
      prisma.driverActivity.upsert({
        where: { id: r.id },
        update: {},
        create: {
          id: r.id,
          driveractivitytypeid: r.driveractivitytypeid,
          driverid: r.driverid,
          vehicleid: r.vehicleid,
          tripid: r.tripid,
          personid: r.personid,
          datetime: toDate(r.datetime),
          lat: toDecimal(r.lat),
          lng: toDecimal(r.lng),
          odometer: r.odometer == null ? null : Number(r.odometer),
          speed: toDecimal(r.speed),
          heading: toDecimal(r.heading),
          note: r.note,
          createddate: toDate(r.createddate),
          updateddate: toDate(r.updateddate),
          createdat: toDate(r.createdat),
          updatedat: toDate(r.updatedat),
        },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── VehicleService ──────────────────────────────────────────────────────
  console.log("→ vehicleservice...");
  for (const r of db.vehicleservice ?? []) {
    const res = await upsertSafe(`vehicleservice#${r.id}`, () =>
      prisma.vehicleService.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, vehicleservicetypeid: r.vehicleservicetypeid, vehicleid: r.vehicleid, companyid: r.companyid, vehicleservicetaskid: r.vehicleservicetaskid, datetime: toDate(r.datetime), description: r.description, odometer: toDecimal(r.odometer), totalamount: toDecimal(r.totalamount), invoicenumber: r.invoicenumber, nextserviceat: toDate(r.nextserviceat), nextserviceodometer: toDecimal(r.nextserviceodometer), note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Transaction ─────────────────────────────────────────────────────────
  console.log("→ transaction...");
  for (const r of db.transaction ?? []) {
    const res = await upsertSafe(`transaction#${r.id}`, () =>
      prisma.transaction.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, transactiontypeid: r.transactiontypeid, date: toDate(r.date), fromtypeid: r.fromtypeid, fromid: r.fromid, totypeid: r.totypeid, toid: r.toid, amount: toDecimal(r.amount), description: r.description, vehicleid: r.vehicleid, personid: r.personid, note: r.note, createddate: toDate(r.createddate), updateddate: toDate(r.updateddate), officialtypeid: r.officialtypeid },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Person ─────────────────────────────────────────────────────────────
  console.log("→ person...");
  for (const r of db.person ?? []) {
    const res = await upsertSafe(`person#${r.id}`, () =>
      prisma.person.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, persontypeid: r.persontypeid, firstname: r.firstname, middlename: r.middlename, lastname: r.lastname, email: r.email, phone: r.phone, street: r.street, housenr: r.housenr, pobox: r.pobox, city: r.city, country: r.country, taxid: r.taxid, bankname: r.bankname, iban: r.iban, bic: r.bic, dateofbirth: toDate(r.dateofbirth), birthcity: r.birthcity, birthcountry: r.birthcountry, nationality: r.nationality, gender: r.gender, note: r.note, createdat: toDate(r.createdat) ?? new Date(), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── User ───────────────────────────────────────────────────────────────
  console.log("→ user...");
  for (const r of db.user ?? []) {
    const pwd = (r.password ?? "").replace(/^\$2y\$/, "$2b$");
    const res = await upsertSafe(`user#${r.id}`, () =>
      prisma.user.upsert({
        where: { username: r.username },
        update: {},
        create: { id: r.id, username: r.username, password: pwd, personid: r.personid, active: Boolean(r.active) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Driver ─────────────────────────────────────────────────────────────
  console.log("→ driver...");
  for (const r of db.driver ?? []) {
    const pid = personIds.has(r.personid) ? r.personid : null;
    const res = await upsertSafe(`driver#${r.id}`, () =>
      prisma.driver.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, drivertypeid: r.drivertypeid, personid: pid, licensecategoryid: r.licensecategoryid, licenseissuedate: toDate(r.licenseissuedate), licenseexpirydate: toDate(r.licenseexpirydate), pendorsementissuedate: toDate(r.pendorsementissuedate), pendorsementexpirydate: toDate(r.pendorsementexpirydate), note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Employee ───────────────────────────────────────────────────────────
  console.log("→ employee...");
  for (const r of db.employee ?? []) {
    const pid = personIds.has(r.personid) ? r.personid : null;
    const res = await upsertSafe(`employee#${r.id}`, () =>
      prisma.employee.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, personid: pid, employeetypeid: r.employeetypeid, employeenumber: r.employeenumber, jobtitleid: r.jobtitleid, departmentid: r.departmentid, businessemail: r.businessemail, businessphone: r.businessphone, hiredate: toDate(r.hiredate), terminationdate: toDate(r.terminationdate), note: r.note, maxmonthlyhours: r.maxmonthlyhours, maxweeklyhours: r.maxweeklyhours, workinghoursschichtid: r.workinghoursschichtid, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Vehicle ────────────────────────────────────────────────────────────
  console.log("→ vehicle...");
  for (const r of db.vehicle ?? []) {
    const res = await upsertSafe(`vehicle#${r.id}`, () =>
      prisma.vehicle.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, licenseplate: r.licenseplate, vehicletypeid: r.vehicletypeid, makeid: r.makeid, modelid: r.modelid, colorid: r.colorid, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── WorkingHours ───────────────────────────────────────────────────────
  console.log("→ workinghours...");
  for (const r of db.workinghours ?? []) {
    const res = await upsertSafe(`wh#${r.id}`, () =>
      prisma.workingHours.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, employeeid: r.employeeid, vehicleid: r.vehicleid, date: toDate(r.date), firsttripat: toDate(r.firsttripat), lasttripat: toDate(r.lasttripat), workstartat: toDate(r.workstartat), workendat: toDate(r.workendat), breakduration: r.breakduration != null ? String(r.breakduration) : null, hourstotal: toDecimal(r.hourstotal), startkm: toDecimal(r.hours0004), endkm: toDecimal(r.hours2006), recordedat: toDate(r.recordedat), note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── VehicleLocation ────────────────────────────────────────────────────
  console.log("→ vehiclelocation...");
  for (const r of db.vehiclelocation ?? []) {
    const res = await upsertSafe(`vl#${r.id}`, () =>
      prisma.vehicleLocation.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, vehicleid: vehicleIds.has(r.vehicleid) ? r.vehicleid : null, driverid: driverIds.has(r.driverid) ? r.driverid : null, tripid: r.tripid, datetime: toDate(r.datetime) ?? new Date(), lat: new Prisma.Decimal(r.lat ?? 0), lng: new Prisma.Decimal(r.lng ?? 0), speed: new Prisma.Decimal(r.speed ?? 0), heading: r.heading, accuracy: toDecimal(r.accuracy), note: r.note, createddate: toDate(r.createddate), updateddate: toDate(r.updateddate) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Trip ───────────────────────────────────────────────────────────────
  console.log("→ trip (1737 rows)...");
  const trips = db.trip ?? [];
  for (let i = 0; i < trips.length; i++) {
    const r = trips[i];
    const did = driverIds.has(r.driverid) ? r.driverid : null;
    const vid = vehicleIds.has(r.vehicleid) ? r.vehicleid : null;
    const res = await upsertSafe(`trip#${r.id}`, () =>
      prisma.trip.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, triptypeid: r.triptypeid, tripstatusid: r.tripstatusid, tripsourceid: r.tripsourceid, vehicleid: vid, driverid: did, submittedat: toDate(r.submittedat), transmittedat: toDate(r.transmittedat), respondedat: toDate(r.respondedat), pickedupat: toDate(r.pickedupat), arrivedat: toDate(r.arrivedat), latvehiclelocationatorder: toDecimal(r.latvehiclelocationatorder), lngvehiclelocationatorder: toDecimal(r.lngvehiclelocationatorder), latpickuplocation: toDecimal(r.latpickuplocation), lngpickuplocation: toDecimal(r.lngpickuplocation), latdropofflocation: toDecimal(r.latdropofflocation), lngdropofflocation: toDecimal(r.lngdropofflocation), pickupdistance: toDecimal(r.pickupdistance), tripdistance: toDecimal(r.tripdistance), fare: toDecimal(r.fare), paymenttypeid: r.paymenttypeid, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
    if ((i + 1) % 200 === 0) console.log(`   ${i + 1}/${trips.length}`);
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Performance ────────────────────────────────────────────────────────
  console.log("→ performance...");
  for (const r of db.performance ?? []) {
    const did = driverIds.has(r.driverid) ? r.driverid : null;
    const res = await upsertSafe(`perf#${r.id}`, () =>
      prisma.performance.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, driverid: did, week: r.week, netearningsbolt: toDecimal(r.netearningsbolt), tollfeesbolt: toDecimal(r.tollfeesbolt), ridertipsbolt: toDecimal(r.ridertipsbolt), collectedcashbolt: toDecimal(r.collectedcashbolt), earningsperformancebolt: toDecimal(r.earningsperformancebolt), finishedridesbolt: r.finishedridesbolt, onlinetimebolt: toDecimal(r.onlinetimebolt), totalridedistancebolt: toDecimal(r.totalridedistancebolt), totalacceptanceratebolt: toDecimal(r.totalacceptanceratebolt), netearningsuber: toDecimal(r.netearningsuber), tollfeesuber: toDecimal(r.tollfeesuber), ridertipsuber: toDecimal(r.ridertipsuber), collectedcashuber: toDecimal(r.collectedcashuber), earningsperformanceuber: toDecimal(r.earningsperformanceuber), finishedridesuber: r.finishedridesuber, onlinetimeuber: toDecimal(r.onlinetimeuber), totalridedistanceuber: toDecimal(r.totalridedistanceuber), totalacceptancerateuber: toDecimal(r.totalacceptancerateuber), note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`); ok = 0; skip = 0;

  // ── Ticket ─────────────────────────────────────────────────────────────
  console.log("→ ticket...");
  for (const r of db.ticket ?? []) {
    const res = await upsertSafe(`ticket#${r.id}`, () =>
      prisma.ticket.upsert({
        where: { id: r.id },
        update: {},
        create: { id: r.id, tickettypeid: r.tickettypeid, ticketcategoryid: r.ticketcategoryid, createdbyid: r.createdbyid, requesterid: r.requesterid, assignedtoid: r.assignedtoid, priorityid: r.priorityid, ticketstatusid: r.ticketstatusid, sortorder: r.sortorder, subject: r.subject, description: r.description, dueat: toDate(r.dueat), scheduledat: toDate(r.scheduledat), resolvedat: toDate(r.resolvedat), closedat: toDate(r.closedat), driverid: r.driverid, vehicleid: r.vehicleid, note: r.note, createdat: toDate(r.createdat), updatedat: toDate(r.updatedat) },
      })
    );
    res ? ok++ : skip++;
  }
  console.log(`   ✓ ${ok} ok, ${skip} skipped`);

  console.log("\n✅ Migration tamamlandi!");
}

main()
  .catch((e) => { console.error("FATAL:", e.message); process.exit(1); })
  .finally(() => prisma.$disconnect());
