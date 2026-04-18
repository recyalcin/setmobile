import { PrismaClient } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

async function main() {
  const hashedPassword = await bcrypt.hash("admin123", 10);

  const adminPerson = await prisma.person.upsert({
    where: { id: 1 },
    update: {},
    create: { firstname: "Admin", lastname: "User", email: "admin@setmobile.com", persontypeid: 1 },
  });

  await prisma.user.upsert({
    where: { username: "admin" },
    update: {},
    create: { username: "admin", password: hashedPassword, personid: adminPerson.id, active: true },
  });

  await prisma.employee.upsert({
    where: { id: 1 },
    update: {},
    create: { personid: adminPerson.id, employeenumber: "EMP-001" },
  });

  const driverPerson = await prisma.person.upsert({
    where: { id: 2 },
    update: {},
    create: { firstname: "Test", lastname: "Surucu", email: "driver@setmobile.com", persontypeid: 2 },
  });

  await prisma.user.upsert({
    where: { username: "driver" },
    update: {},
    create: { username: "driver", password: await bcrypt.hash("driver123", 10), personid: driverPerson.id, active: true },
  });

  await prisma.driver.upsert({
    where: { id: 1 },
    update: {},
    create: { personid: driverPerson.id },
  });

  await prisma.employee.upsert({
    where: { id: 2 },
    update: {},
    create: { personid: driverPerson.id, employeenumber: "EMP-002" },
  });

  console.log(`
Seed tamamlandi!
Admin  -> kullanici: admin   | sifre: admin123
Surucu -> kullanici: driver  | sifre: driver123
`);
}

main()
  .catch((e) => { console.error(e); process.exit(1); })
  .finally(() => prisma.$disconnect());
