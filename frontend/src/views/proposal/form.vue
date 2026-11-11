<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';

import { getProposal, getProposals, submitProposal, updateProposal } from '@/api/proposal';
import { getContacts } from '@/api/contact';
import { getCustomers, getParts } from '@/api/masterdata';
import ImageUploader from './ImageUploader.vue';
import { useUserStore } from '@/store/user';
import { IMAGE_SLOTS, PACK_MATERIAL_OPTIONS } from '@/utils/constants';
import { numberText } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const proposalId = route.params.id;
const loading = ref(false);
const saving = ref(false);
const detail = ref(null);
const attachments = ref([]);
const customers = ref([]);
const contacts = ref([]);
const parts = ref([]);

const copyVisible = ref(false);
const copyLoading = ref(false);
const copyCandidates = ref([]);

const form = reactive({
  customer_id: '',
  customer_name: '',
  customer_address: '',
  customer_contact: '',
  customer_phone: '',
  customer_email: '',
  customer_fax: '',
  supplier_id: '',
  contact: '',
  phone: '',
  supplier_email: '',
  supplier_fax: '',
  part_no: '',
  part_material: '',
  pack_box_no: '',
  pack_material: '',
  part_weight: 0,
  part_size_l: 0,
  part_size_w: 0,
  part_size_h: 0,
  box_size_l: 0,
  box_size_w: 0,
  box_size_h: 0,
  gross_weight: 0,
  returnable: null,
  dunnage_returnable: null,
  with_pallet: null,
  packing_qty: 0,
  packaging_instruction: '',
  description: '',
  pallet_size_l: 0,
  pallet_size_w: 0,
  pallet_size_h: 0,
  pallet_layer_qty: 0,
  pallet_layer_count: 0,
});

const canEdit = computed(() => detail.value?.permissions?.edit !== false);
const partLocked = computed(() => !userStore.isAdmin);

const specText = computed(
  () =>
    `${numberText(form.box_size_l)}*${numberText(form.box_size_w)}*${numberText(form.box_size_h)}mm`,
);
const palletPartsQty = computed(() => {
  if (form.with_pallet !== 1) return 0;
  return (Number(form.pallet_layer_qty) || 0) * (Number(form.pallet_layer_count) || 0) * (Number(form.packing_qty) || 0);
});

function attachmentOf(type) {
  return attachments.value.find((item) => item.attach_type === type) || null;
}

async function load() {
  loading.value = true;

  try {
    const data = await getProposal(proposalId);
    detail.value = data;
    attachments.value = data.attachments || [];

    Object.keys(form).forEach((key) => {
      if (Object.prototype.hasOwnProperty.call(data, key)) {
        form[key] = data[key];
      }
    });

    customers.value = (await getCustomers()) || [];

    const [contactList, partList] = await Promise.all([
      getContacts({ supplier_id: data.supplier_id }),
      getParts({ supplier_id: data.supplier_id }),
    ]);

    contacts.value = contactList || [];
    parts.value = partList || [];
  } finally {
    loading.value = false;
  }
}

onMounted(load);

function onCustomerChange(customerId) {
  const customer = customers.value.find((item) => item.id === customerId);
  if (!customer) return;

  form.customer_name = customer.name;
  form.customer_address = customer.address;
  form.customer_contact = customer.contact;
  form.customer_phone = customer.phone;
  form.customer_email = customer.email;
  form.customer_fax = customer.fax;
}

function onContactChange(name) {
  const contact = contacts.value.find((item) => item.name === name);
  if (!contact) return;

  form.phone = contact.phone;
  form.supplier_email = contact.email;
  form.supplier_fax = contact.fax;
}

function onPartChange(partNo) {
  const part = parts.value.find((item) => item.part_no === partNo);
  if (!part) return;

  detail.value.part_name = part.part_name;
  detail.value.project_model = part.project_name;
}

watch(
  () => form.with_pallet,
  (value) => {
    if (value !== 1) {
      form.pallet_size_l = 0;
      form.pallet_size_w = 0;
      form.pallet_size_h = 0;
      form.pallet_layer_qty = 0;
      form.pallet_layer_count = 0;
    }
  },
);

function payload() {
  return {
    customer_id: form.customer_id || null,
    customer_name: form.customer_name,
    customer_address: form.customer_address,
    customer_contact: form.customer_contact,
    customer_phone: form.customer_phone,
    customer_email: form.customer_email,
    customer_fax: form.customer_fax,
    supplier_id: form.supplier_id,
    contact: form.contact,
    phone: form.phone,
    supplier_email: form.supplier_email,
    supplier_fax: form.supplier_fax,
    project_model: detail.value?.project_model || '',
    part_no: form.part_no,
    part_name: detail.value?.part_name || '',
    part_material: form.part_material,
    pack_box_no: form.pack_box_no,
    pack_material: form.pack_material,
    part_weight: Number(form.part_weight) || 0,
    part_size_l: Number(form.part_size_l) || 0,
    part_size_w: Number(form.part_size_w) || 0,
    part_size_h: Number(form.part_size_h) || 0,
    box_size_l: Number(form.box_size_l) || 0,
    box_size_w: Number(form.box_size_w) || 0,
    box_size_h: Number(form.box_size_h) || 0,
    gross_weight: Number(form.gross_weight) || 0,
    returnable: form.returnable,
    dunnage_returnable: form.dunnage_returnable,
    with_pallet: form.with_pallet,
    packing_qty: Number(form.packing_qty) || 0,
    packaging_instruction: form.packaging_instruction,
    description: form.description,
    pallet_size_l: Number(form.pallet_size_l) || 0,
    pallet_size_w: Number(form.pallet_size_w) || 0,
    pallet_size_h: Number(form.pallet_size_h) || 0,
    pallet_layer_qty: Number(form.pallet_layer_qty) || 0,
    pallet_layer_count: Number(form.pallet_layer_count) || 0,
  };
}

async function save(andSubmit = false) {
  if (!form.customer_id) {
    ElMessage.warning('请选择客户');
    return;
  }

  saving.value = true;

  try {
    await updateProposal(proposalId, payload());

    if (andSubmit) {
      await submitProposal(proposalId);
      ElMessage.success('已提交评审');
      router.push(`/proposals/${proposalId}`);
      return;
    }

    ElMessage.success('保存成功');
    await load();
  } finally {
    saving.value = false;
  }
}

async function openCopy() {
  copyVisible.value = true;
  copyLoading.value = true;

  try {
    const data = await getProposals({ page: 1, page_size: 100 });
    copyCandidates.value = (data.list || []).filter((item) => item.id !== proposalId);
  } finally {
    copyLoading.value = false;
  }
}

async function applyCopy(row) {
  const source = await getProposal(row.id);

  // 不覆盖：零件号 / 零件名称 / 项目车型 / 供应商资料
  Object.assign(form, {
    customer_id: source.customer_id || '',
    customer_name: source.customer_name,
    customer_address: source.customer_address,
    customer_contact: source.customer_contact,
    customer_phone: source.customer_phone,
    customer_email: source.customer_email,
    customer_fax: source.customer_fax,
    part_material: source.part_material,
    pack_box_no: source.pack_box_no,
    pack_material: source.pack_material,
    part_weight: source.part_weight,
    part_size_l: source.part_size_l,
    part_size_w: source.part_size_w,
    part_size_h: source.part_size_h,
    box_size_l: source.box_size_l,
    box_size_w: source.box_size_w,
    box_size_h: source.box_size_h,
    gross_weight: source.gross_weight,
    returnable: source.returnable,
    dunnage_returnable: source.dunnage_returnable,
    with_pallet: source.with_pallet,
    packing_qty: source.packing_qty,
    packaging_instruction: source.packaging_instruction,
    description: source.description,
    pallet_size_l: source.pallet_size_l,
    pallet_size_w: source.pallet_size_w,
    pallet_size_h: source.pallet_size_h,
    pallet_layer_qty: source.pallet_layer_qty,
    pallet_layer_count: source.pallet_layer_count,
  });

  copyVisible.value = false;
  ElMessage.success('已套用所选方案的包装信息');
}

function onImageChanged(attachment) {
  attachments.value = attachments.value.filter((item) => item.attach_type !== attachment.attach_type);
  attachments.value.push(attachment);
}

function onImageRemoved(attachment) {
  attachments.value = attachments.value.filter((item) => item.id !== attachment.id);
}
</script>

<template>
  <div v-loading="loading">
    <div v-if="detail" class="page-card">
      <div class="page-toolbar">
        <el-button text @click="router.push(`/proposals/${proposalId}`)">返回详情</el-button>
        <h2 style="margin: 0; font-size: 18px">编辑方案 {{ detail.proposal_no }}</h2>
        <el-tag type="info" effect="plain">V{{ detail.version }}</el-tag>
        <div class="spacer"></div>
        <el-button :disabled="!canEdit" @click="openCopy">复制其他方案</el-button>
        <el-button :disabled="!canEdit" :loading="saving" @click="save(false)">保存草稿</el-button>
        <el-button type="primary" :disabled="!canEdit" :loading="saving" @click="save(true)">保存并提交</el-button>
      </div>

      <el-alert type="info" :closable="false" show-icon style="margin-bottom: 14px"
        title="规格尺寸、数量、每托盘零件数量与填写日期由系统自动计算，无需手工填写。" />

      <h3 class="section-title">基本信息</h3>
      <el-form label-width="112px" label-position="right">
        <div class="form-grid">
          <el-form-item label="客户">
            <el-select v-model="form.customer_id" filterable placeholder="请选择客户" style="width: 100%"
              @change="onCustomerChange">
              <el-option v-for="item in customers" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="填写日期">
            <el-input :model-value="detail.write_date" disabled />
          </el-form-item>
          <el-form-item label="客户地址">
            <el-input v-model="form.customer_address" disabled />
          </el-form-item>
          <el-form-item label="客户联系人">
            <el-input v-model="form.customer_contact" disabled />
          </el-form-item>
          <el-form-item label="客户电话">
            <el-input v-model="form.customer_phone" disabled />
          </el-form-item>
          <el-form-item label="客户邮箱">
            <el-input v-model="form.customer_email" disabled />
          </el-form-item>
          <el-form-item label="客户传真">
            <el-input v-model="form.customer_fax" disabled />
          </el-form-item>
          <el-form-item label="项目车型">
            <el-input :model-value="detail.project_model" disabled />
          </el-form-item>
          <el-form-item label="零件号">
            <el-select v-model="form.part_no" filterable :disabled="partLocked" style="width: 100%"
              @change="onPartChange">
              <el-option v-for="item in parts" :key="item.id" :label="item.part_no" :value="item.part_no" />
            </el-select>
          </el-form-item>
          <el-form-item label="零件名称">
            <el-input :model-value="detail.part_name" disabled />
          </el-form-item>
          <el-form-item label="零件材料">
            <el-input v-model="form.part_material" maxlength="100" placeholder="如：铝合金" />
          </el-form-item>
        </div>

        <h3 class="section-title">供应商资料</h3>
        <div class="form-grid">
          <el-form-item label="供应商">
            <el-input :model-value="detail.supplier_name" disabled />
          </el-form-item>
          <el-form-item label="供应商代码">
            <el-input :model-value="detail.supplier_code" disabled />
          </el-form-item>
          <el-form-item label="供应商地址">
            <el-input :model-value="detail.supplier_address" disabled />
          </el-form-item>
          <el-form-item label="联系人">
            <el-select v-model="form.contact" filterable allow-create default-first-option clearable
              placeholder="选择或输入联系人" style="width: 100%" @change="onContactChange">
              <el-option v-for="item in contacts" :key="item.id" :label="item.name" :value="item.name" />
            </el-select>
          </el-form-item>
          <el-form-item label="联系电话">
            <el-input v-model="form.phone" maxlength="30" />
          </el-form-item>
          <el-form-item label="联系邮箱">
            <el-input v-model="form.supplier_email" maxlength="100" />
          </el-form-item>
          <el-form-item label="联系传真">
            <el-input v-model="form.supplier_fax" maxlength="30" />
          </el-form-item>
        </div>

        <h3 class="section-title">包装信息</h3>
        <div class="form-grid">
          <el-form-item label="规格尺寸">
            <el-input :model-value="specText" disabled />
          </el-form-item>
          <el-form-item label="数量">
            <el-input :model-value="String(form.packing_qty)" disabled />
          </el-form-item>
          <el-form-item label="产品净重(kg)">
            <el-input-number v-model="form.part_weight" :min="0" :precision="3" :step="0.1" style="width: 100%" />
          </el-form-item>
          <el-form-item label="产品外形长(mm)">
            <el-input-number v-model="form.part_size_l" :min="0" :precision="0" style="width: 100%" />
          </el-form-item>
          <el-form-item label="产品外形宽(mm)">
            <el-input-number v-model="form.part_size_w" :min="0" :precision="0" style="width: 100%" />
          </el-form-item>
          <el-form-item label="产品外形高(mm)">
            <el-input-number v-model="form.part_size_h" :min="0" :precision="0" style="width: 100%" />
          </el-form-item>
          <el-form-item label="包装箱编号">
            <el-input v-model="form.pack_box_no" maxlength="50" />
          </el-form-item>
          <el-form-item label="包装箱材料">
            <el-select v-model="form.pack_material" placeholder="请选择" style="width: 100%">
              <el-option v-for="item in PACK_MATERIAL_OPTIONS" :key="item.value" :label="item.label"
                :value="item.value" />
            </el-select>
          </el-form-item>
          <el-form-item label="包装箱外长(mm)">
            <el-input-number v-model="form.box_size_l" :min="0" :precision="0" style="width: 100%" />
          </el-form-item>
          <el-form-item label="包装箱外宽(mm)">
            <el-input-number v-model="form.box_size_w" :min="0" :precision="0" style="width: 100%" />
          </el-form-item>
          <el-form-item label="包装箱外高(mm)">
            <el-input-number v-model="form.box_size_h" :min="0" :precision="0" style="width: 100%" />
          </el-form-item>
          <el-form-item label="毛重(kg)">
            <el-input-number v-model="form.gross_weight" :min="0" :precision="3" :step="0.1" style="width: 100%" />
          </el-form-item>
          <el-form-item label="存放数量(件/箱)">
            <el-input-number v-model="form.packing_qty" :min="0" :precision="0" style="width: 100%" />
          </el-form-item>
          <el-form-item label="包装箱是否返回">
            <el-radio-group v-model="form.returnable">
              <el-radio :value="1">是</el-radio>
              <el-radio :value="0">否</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="内衬是否返回">
            <el-radio-group v-model="form.dunnage_returnable">
              <el-radio :value="1">是</el-radio>
              <el-radio :value="0">否</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="是否带托盘交付">
            <el-radio-group v-model="form.with_pallet">
              <el-radio :value="1">是</el-radio>
              <el-radio :value="0">否</el-radio>
            </el-radio-group>
          </el-form-item>
        </div>

        <template v-if="form.with_pallet === 1">
          <h3 class="section-title">托盘信息</h3>
          <div class="form-grid">
            <el-form-item label="满托盘外长(mm)">
              <el-input-number v-model="form.pallet_size_l" :min="0" :precision="0" style="width: 100%" />
            </el-form-item>
            <el-form-item label="满托盘外宽(mm)">
              <el-input-number v-model="form.pallet_size_w" :min="0" :precision="0" style="width: 100%" />
            </el-form-item>
            <el-form-item label="满托盘外高(mm)">
              <el-input-number v-model="form.pallet_size_h" :min="0" :precision="0" style="width: 100%" />
            </el-form-item>
            <el-form-item label="每层包装箱数量">
              <el-input-number v-model="form.pallet_layer_qty" :min="0" :precision="0" style="width: 100%" />
            </el-form-item>
            <el-form-item label="堆码层数">
              <el-input-number v-model="form.pallet_layer_count" :min="0" :precision="0" style="width: 100%" />
            </el-form-item>
            <el-form-item label="每托盘零件数量">
              <el-input :model-value="String(palletPartsQty)" disabled />
            </el-form-item>
          </div>
        </template>

        <h3 class="section-title">包装操作说明</h3>
        <el-form-item label-width="0">
          <el-input v-model="form.packaging_instruction" type="textarea" :rows="4" maxlength="2000"
            show-word-limit placeholder="填写包装操作要点，可留空" />
        </el-form-item>

        <h3 class="section-title">包装图片</h3>
        <div class="image-grid">
          <ImageUploader v-for="slot in IMAGE_SLOTS" :key="slot.type" :label="slot.label" :hint="slot.hint"
            :attach-type="slot.type" :attachment="attachmentOf(slot.type)" :proposal-id="proposalId"
            :editable="canEdit" @changed="onImageChanged" @removed="onImageRemoved" />
        </div>

        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px">
          <el-button @click="router.push(`/proposals/${proposalId}`)">取消</el-button>
          <el-button :disabled="!canEdit" :loading="saving" @click="save(false)">保存草稿</el-button>
          <el-button type="primary" :disabled="!canEdit" :loading="saving" @click="save(true)">保存并提交</el-button>
        </div>
      </el-form>
    </div>

    <el-dialog v-model="copyVisible" title="复制其他方案" width="760px">
      <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px"
        title="复制只套用包装信息与客户资料，不会覆盖零件号、零件名称、项目车型与供应商资料。" />
      <el-table :data="copyCandidates" v-loading="copyLoading" size="small" height="380">
        <el-table-column prop="proposal_no" label="方案编号" width="150" />
        <el-table-column prop="part_no" label="零件号" width="140" />
        <el-table-column prop="part_name" label="零件名称" min-width="150" show-overflow-tooltip />
        <el-table-column prop="status_label" label="状态" width="100" />
        <el-table-column label="操作" width="90">
          <template #default="{ row }">
            <el-button link type="primary" @click="applyCopy(row)">套用</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>
  </div>
</template>
