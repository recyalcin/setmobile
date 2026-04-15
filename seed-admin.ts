
import { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function main() {
  const email = 'admin@setmobil.com';
  const password = 'admin123';
  console.log('Hashing password:', password);
  const hashedPassword = await bcrypt.hash(password, 10);

  // Create or get Person first
  const person = await prisma.person.upsert({
    where: { id: 1 },
    update: {
      firstname: 'System',
      lastname: 'Admin',
      email: email
    },
    create: {
      id: 1,
      firstname: 'System',
      lastname: 'Admin',
      email: email,
      persontypeid: 1 // Admin type
    }
  });

  const admin = await prisma.user.upsert({
    where: { username: email },
    update: {
      password: hashedPassword,
      active: true,
      personid: person.id
    },
    create: {
      username: email,
      password: hashedPassword,
      active: true,
      personid: person.id,
      note: 'Initial Admin'
    }
  });

  // Ensure Employee record exists
  await prisma.employee.upsert({
    where: { id: 1 },
    update: { personid: person.id },
    create: {
      id: 1,
      personid: person.id,
      employeenumber: 'ADMIN-001'
    }
  });

  // Ensure Driver record exists
  await prisma.driver.upsert({
    where: { id: 1 },
    update: { personid: person.id },
    create: {
      id: 1,
      personid: person.id
    }
  });

  console.log('Admin user created/updated:', admin.username);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
